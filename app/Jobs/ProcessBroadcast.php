<?php

namespace App\Jobs;

use App\Actions\Messaging\SendMessage;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Support\MessagePayloadBuilder;
use App\Support\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Builds one personalised template message per queued recipient and hands each to SendMessageJob
 * (rate-limited). Runs in chunks so a 10k broadcast never holds one worker for long.
 */
class ProcessBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $broadcastId) {}

    public function handle(SendMessage $send): void
    {
        $broadcast = Broadcast::with(['template', 'phoneNumber.account'])->find($this->broadcastId);
        if (! $broadcast || $broadcast->status === Broadcast::STATUS_CANCELLED) {
            return;
        }
        if (! $broadcast->template || ! $broadcast->template->isSendable()) {
            $broadcast->forceFill(['status' => Broadcast::STATUS_FAILED, 'error' => 'Template is missing or not APPROVED.'])->save();

            return;
        }

        $broadcast->forceFill(['status' => Broadcast::STATUS_SENDING])->save();

        $chunk = (int) config('whatsapp.broadcast.chunk', 200);
        $recipients = $broadcast->recipients()->where('status', 'queued')->whereNull('message_id')->with('contact')->orderBy('id')->limit($chunk)->get();

        foreach ($recipients as $recipient) {
            $contact = $recipient->contact;
            if (! $contact || ! $contact->canReceiveBusinessInitiated($broadcast->require_opt_in)) {
                $recipient->update(['status' => 'skipped', 'skip_reason' => $contact ? 'opted_out' : 'missing_contact']);

                continue;
            }

            try {
                $params = self::personalise($broadcast->template_params ?? [], $contact->tokenValues());
                $components = MessagePayloadBuilder::templateComponents($broadcast->template, $params);
                $payload = (new MessagePayloadBuilder('+'.$contact->wa_id))->template($broadcast->template->name, $broadcast->template->language, $components);
                $preview = TemplateRenderer::summary($broadcast->template, $components);

                $message = $send->record(null, $broadcast->phoneNumber, $payload, $preview, $broadcast->template, $contact, 'broadcast', ['broadcast_id' => $broadcast->id], queue: true);
                $recipient->update(['message_id' => $message->id]);

                SendMessageJob::dispatch($message->id);
            } catch (\Throwable $e) {
                Log::warning('Broadcast recipient failed to build', ['broadcast' => $broadcast->id, 'recipient' => $recipient->id, 'error' => $e->getMessage()]);
                $recipient->update(['status' => 'failed', 'error' => $e->getMessage()]);
            }
        }

        $broadcast->refreshCounters();

        // More to do? Re-queue ourselves; otherwise SendMessageJob/webhooks finish the counters.
        if ($broadcast->recipients()->where('status', 'queued')->whereNull('message_id')->exists()) {
            self::dispatch($broadcast->id)->delay(now()->addSeconds(2));
        }
    }

    /** Replace {{contact.name}} / {{name}} tokens inside header/body/button values. */
    public static function personalise(array $params, array $values): array
    {
        $replace = function ($v) use ($values) {
            if (! is_string($v)) {
                return $v;
            }

            return preg_replace_callback('/\{\{\s*(?:contact\.)?([a-z0-9_]+)\s*\}\}/i', fn ($m) => (string) ($values[$m[1]] ?? ''), $v);
        };

        $out = $params;
        $out['header'] = array_map($replace, $params['header'] ?? []);
        $out['body'] = array_map($replace, $params['body'] ?? []);
        $out['buttons'] = array_map(fn ($b) => array_map($replace, $b), $params['buttons'] ?? []);

        return $out;
    }
}
