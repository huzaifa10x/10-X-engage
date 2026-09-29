<?php

namespace App\Jobs;

use App\Actions\Messaging\SendMessage;
use App\Exceptions\GraphApiException;
use App\Models\BroadcastRecipient;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a 'queued' message. Rate-limited per sending phone number (see AppServiceProvider),
 * retried on transient Graph errors, marked failed on permanent ones.
 */
class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $messageId) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function middleware(): array
    {
        return [(new RateLimited('whatsapp-send'))->dontRelease()];
    }

    public function handle(SendMessage $send): void
    {
        $message = Message::find($this->messageId);
        if (! $message || ! in_array($message->status, ['queued', 'pending'], true)) {
            return;
        }

        try {
            $send->deliver($message);
        } catch (GraphApiException $e) {
            // Rate limit / transient service errors are retried; everything else is final.
            if (in_array($e->graphCode, [4, 80007, 130429, 131048, 131056, 368, 1, 2], true) && $this->attempts() < $this->tries) {
                $message->forceFill(['status' => 'queued'])->save();
                $this->release($this->backoff()[$this->attempts() - 1] ?? 60);

                return;
            }
        } finally {
            $this->syncRecipient($message->fresh());
        }
    }

    public function failed(\Throwable $e): void
    {
        if ($message = Message::find($this->messageId)) {
            if ($message->status === 'queued') {
                $message->forceFill(['status' => 'failed', 'failed_at' => now(), 'error_message' => $e->getMessage()])->save();
            }
            $this->syncRecipient($message);
        }
    }

    protected function syncRecipient(Message $message): void
    {
        if (! $message->broadcast_id) {
            return;
        }

        $status = match ($message->status) {
            'accepted', 'pending' => 'sent',
            'queued' => 'queued',
            default => $message->status,
        };

        BroadcastRecipient::where('message_id', $message->id)->update(['status' => $status, 'error' => $message->error_message]);
        $message->broadcast?->refreshCounters();
    }
}
