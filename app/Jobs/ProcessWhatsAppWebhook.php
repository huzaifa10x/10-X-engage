<?php

namespace App\Jobs;

use App\Actions\Templates\SyncTemplates;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PhoneNumber;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes one webhook change. Field handlers follow the payload references in the
 * Cloud API ("Webhook Payload Reference") and Embedded Signup ("Webhooks") collections.
 */
class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        $event = WebhookEvent::find($this->eventId);
        if (! $event || $event->status === 'processed') {
            return;
        }

        $change = $event->payload['change'] ?? [];
        $value = $change['value'] ?? [];

        try {
            match ($event->field) {
                'messages' => $this->handleMessages($event, $value),
                'message_template_status_update' => $this->handleTemplateStatus($event, $value),
                'account_update' => $this->handleAccountUpdate($event, $value),
                'account_review_update' => $this->handleAccountReview($event, $value),
                'phone_number_name_update' => $this->handlePhoneNameUpdate($event, $value),
                'phone_number_quality_update' => $this->handlePhoneQualityUpdate($event, $value),
                default => $event->update(['status' => 'ignored', 'processed_at' => now()]),
            };

            if ($event->status !== 'ignored') {
                $event->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
            }
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', ['event' => $event->id, 'exception' => $e]);
            $event->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /* ----------------------------------------------------------------- messages */

    protected function handleMessages(WebhookEvent $event, array $value): void
    {
        $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
        $phone = PhoneNumber::where('phone_number_id', $phoneNumberId)->with('account')->first();

        if (! $phone) {
            $event->update(['status' => 'ignored', 'error' => "Unknown phone_number_id {$phoneNumberId}"]);

            return;
        }

        $workspaceId = $phone->account->workspace_id;

        // Status updates for messages we sent: sent -> delivered -> read | failed | deleted
        foreach ($value['statuses'] ?? [] as $status) {
            $this->applyStatus($status);
        }

        // Inbound messages
        $profiles = collect($value['contacts'] ?? [])->keyBy('wa_id');

        foreach ($value['messages'] ?? [] as $incoming) {
            $waId = Contact::normalizeWaId($incoming['from'] ?? '');
            $contact = Contact::firstOrCreate(['workspace_id' => $workspaceId, 'wa_id' => $waId]);

            $profileName = $profiles[$waId]['profile']['name'] ?? null;
            $contact->update(array_filter(['name' => $profileName, 'last_inbound_at' => now()]));

            Message::firstOrCreate(
                ['wamid' => $incoming['id']],
                [
                    'workspace_id' => $workspaceId,
                    'phone_number_id' => $phone->id,
                    'contact_id' => $contact->id,
                    'direction' => Message::DIRECTION_INBOUND,
                    'type' => $incoming['type'] ?? 'unknown',
                    'status' => 'received',
                    'to' => $phone->normalizedNumber(),
                    'from' => $waId,
                    'context_wamid' => $incoming['context']['id'] ?? null,
                    'preview' => $this->inboundPreview($incoming),
                    'payload' => $incoming,
                    'received_at' => isset($incoming['timestamp']) ? Carbon::createFromTimestamp((int) $incoming['timestamp']) : now(),
                    'error_code' => $incoming['errors'][0]['code'] ?? null,
                    'error_title' => $incoming['errors'][0]['title'] ?? null,
                ]
            );
        }
    }

    protected function applyStatus(array $status): void
    {
        $message = Message::where('wamid', $status['id'] ?? '')->first();
        if (! $message) {
            return;
        }

        $state = strtolower($status['status'] ?? '');
        $at = isset($status['timestamp']) ? Carbon::createFromTimestamp((int) $status['timestamp']) : now();

        $rank = ['pending' => 0, 'accepted' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4, 'failed' => 5, 'deleted' => 5];
        $update = [];

        // Never regress (e.g. a late "sent" after "delivered")
        if (($rank[$state] ?? 0) >= ($rank[$message->status] ?? 0)) {
            $update['status'] = $state;
        }

        match ($state) {
            'sent' => $update['sent_at'] = $message->sent_at ?? $at,
            'delivered' => $update['delivered_at'] = $at,
            'read' => $update['read_at'] = $at,
            'failed' => $update += [
                'failed_at' => $at,
                'error_code' => $status['errors'][0]['code'] ?? null,
                'error_title' => $status['errors'][0]['title'] ?? null,
                'error_message' => $status['errors'][0]['message'] ?? ($status['errors'][0]['error_data']['details'] ?? null),
                'error_data' => $status['errors'] ?? null,
            ],
            default => null,
        };

        if (isset($status['conversation'])) {
            $update['conversation_id'] = $status['conversation']['id'] ?? null;
            $update['conversation_origin'] = $status['conversation']['origin']['type'] ?? null;
        }
        if (isset($status['pricing'])) {
            $update['pricing_category'] = $status['pricing']['category'] ?? null;
            $update['billable'] = $status['pricing']['billable'] ?? null;
        }

        $message->update($update);
    }

    protected function inboundPreview(array $m): string
    {
        return match ($m['type'] ?? '') {
            'text' => $m['text']['body'] ?? '',
            'button' => 'Button: '.($m['button']['text'] ?? ''),
            'interactive' => 'Interactive: '.($m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? ''),
            'image', 'video', 'document', 'audio', 'sticker' => ucfirst($m['type']).(isset($m[$m['type']]['caption']) ? ': '.$m[$m['type']]['caption'] : ''),
            'location' => 'Location: '.($m['location']['name'] ?? (($m['location']['latitude'] ?? '').','.($m['location']['longitude'] ?? ''))),
            'reaction' => 'Reaction: '.($m['reaction']['emoji'] ?? ''),
            'contacts' => 'Contact card',
            'order' => 'Order',
            default => ucfirst($m['type'] ?? 'unknown'),
        };
    }

    /* --------------------------------------------- message_template_status_update */

    protected function handleTemplateStatus(WebhookEvent $event, array $value): void
    {
        $account = WhatsAppAccount::where('waba_id', $event->waba_id)->first();
        if (! $account) {
            $event->update(['status' => 'ignored']);

            return;
        }

        $template = MessageTemplate::where('whatsapp_account_id', $account->id)
            ->where(function ($q) use ($value) {
                $q->where('template_id', (string) ($value['message_template_id'] ?? ''))
                    ->orWhere(function ($q) use ($value) {
                        $q->where('name', $value['message_template_name'] ?? '')
                            ->where('language', str_replace('-', '_', $value['message_template_language'] ?? ''));
                    });
            })->first();

        if (! $template) {
            // Unknown locally – pull it from the API so the list stays complete.
            try {
                app(SyncTemplates::class)($account);
            } catch (\Throwable) {
            }

            return;
        }

        $template->update([
            'template_id' => (string) ($value['message_template_id'] ?? $template->template_id),
            'status' => strtoupper($value['event'] ?? $template->status),
            'rejected_reason' => $value['reason'] ?? null,
            'last_status_update_at' => now(),
        ]);
    }

    /* ---------------------------------------------------------- account events */

    protected function handleAccountUpdate(WebhookEvent $event, array $value): void
    {
        $account = WhatsAppAccount::where('waba_id', $event->waba_id)->first();
        if (! $account) {
            $event->update(['status' => 'ignored']);

            return;
        }

        $meta = $account->meta ?? [];
        $meta['last_account_update'] = $value;

        $update = ['meta' => $meta];

        match ($value['event'] ?? '') {
            'DISABLED_UPDATE' => $update += ['status' => WhatsAppAccount::STATUS_DISABLED, 'ban_state' => $value['ban_info']['waba_ban_state'] ?? 'DISABLED'],
            'ACCOUNT_RESTRICTION' => $update += ['ban_state' => 'RESTRICTED'],
            'VERIFIED_ACCOUNT', 'PARTNER_ADDED', 'PARTNER_APP_INSTALLED' => null,
            default => null,
        };

        $account->update($update);
    }

    protected function handleAccountReview(WebhookEvent $event, array $value): void
    {
        WhatsAppAccount::where('waba_id', $event->waba_id)
            ->update(['account_review_status' => strtoupper($value['decision'] ?? 'PENDING')]);
    }

    protected function handlePhoneNameUpdate(WebhookEvent $event, array $value): void
    {
        $phone = $this->findPhoneByDisplayNumber($value['display_phone_number'] ?? null);
        $phone?->update([
            'name_status' => strtoupper($value['decision'] ?? $phone->name_status ?? ''),
            'verified_name' => ($value['decision'] ?? '') === 'APPROVED' ? ($value['requested_verified_name'] ?? $phone->verified_name) : $phone->verified_name,
            'meta' => array_merge($phone->meta ?? [], ['last_name_update' => $value]),
        ]);
    }

    protected function handlePhoneQualityUpdate(WebhookEvent $event, array $value): void
    {
        $phone = $this->findPhoneByDisplayNumber($value['display_phone_number'] ?? null);
        $phone?->update([
            'messaging_limit_tier' => $value['current_limit'] ?? $phone->messaging_limit_tier,
            'meta' => array_merge($phone->meta ?? [], ['last_quality_update' => $value]),
        ]);
    }

    protected function findPhoneByDisplayNumber(?string $displayNumber): ?PhoneNumber
    {
        if (! $displayNumber) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $displayNumber);

        return PhoneNumber::all()->first(fn (PhoneNumber $p) => $p->normalizedNumber() === $digits);
    }
}
