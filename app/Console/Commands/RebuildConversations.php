<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Message;
use Illuminate\Console\Command;

/**
 * Recomputes the inbox summary (last message, unread, 24-hour window) for every contact from the
 * messages table. Use after deploying phase 2 on a database that already had messages, or if the
 * queue processed webhooks while the contacts migration was still pending.
 */
class RebuildConversations extends Command
{
    protected $signature = 'engage:rebuild-conversations {--workspace= : Only this workspace id}';

    protected $description = 'Rebuild contact conversation summaries and 24-hour windows from stored messages';

    public function handle(): int
    {
        $contacts = Contact::query()->when($this->option('workspace'), fn ($q, $w) => $q->where('workspace_id', $w))->get();
        $bar = $this->output->createProgressBar($contacts->count());

        foreach ($contacts as $contact) {
            $last = $contact->messages()->latest('id')->first();
            $lastInbound = $contact->messages()->where('direction', Message::DIRECTION_INBOUND)->latest('id')->first();
            $lastTemplate = config('whatsapp.conversation.template_opens_window')
                ? $contact->messages()->where('direction', Message::DIRECTION_OUTBOUND)->where('type', 'template')->whereIn('status', ['accepted', 'sent', 'delivered', 'read'])->latest('id')->first()
                : null;

            $inboundAt = $lastInbound?->received_at ?? $lastInbound?->created_at;
            $templateAt = $lastTemplate?->created_at;

            $openedBy = null;
            $openedAt = null;
            if ($inboundAt && (! $templateAt || $inboundAt->gte($templateAt))) {
                [$openedBy, $openedAt] = [Contact::OPENED_BY_INBOUND, $inboundAt];
            } elseif ($templateAt) {
                [$openedBy, $openedAt] = [Contact::OPENED_BY_TEMPLATE, $templateAt];
            }

            $contact->forceFill([
                'last_message_at' => $last ? ($last->direction === Message::DIRECTION_INBOUND ? ($last->received_at ?? $last->created_at) : $last->created_at) : null,
                'last_message_preview' => $last?->preview,
                'last_message_direction' => $last?->direction,
                'last_inbound_at' => $inboundAt,
                'phone_number_id' => $contact->phone_number_id ?? $last?->phone_number_id,
                'phone' => $contact->phone ?: '+'.$contact->wa_id,
                'window_expires_at' => $openedAt?->copy()->addHours(Contact::WINDOW_HOURS),
                'window_opened_by' => $openedBy,
            ])->save();

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Rebuilt {$contacts->count()} contact(s).");

        return self::SUCCESS;
    }
}
