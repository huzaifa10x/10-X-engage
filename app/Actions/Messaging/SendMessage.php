<?php

namespace App\Actions\Messaging;

use App\Exceptions\GraphApiException;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\Meta\MessagingService;

/**
 * Persists an outbound message, sends it to POST /{{Phone-Number-ID}}/messages and stores the
 * response ({ contacts:[{input, wa_id}], messages:[{id}] }) or the Graph error.
 *
 * Inline path (inbox / composer): __invoke() = record() + deliver().
 * Queued path (broadcasts): record(..., queue: true) creates a 'queued' row; SendMessageJob calls deliver().
 */
class SendMessage
{
    public function __construct(protected MessagingService $messaging) {}

    public function __invoke(?User $user, PhoneNumber $phone, array $payload, ?string $preview = null, ?MessageTemplate $template = null, ?Contact $contact = null, string $origin = 'agent', array $extra = []): Message
    {
        $message = $this->record($user, $phone, $payload, $preview, $template, $contact, $origin, $extra);

        return $this->deliver($message);
    }

    public function record(?User $user, PhoneNumber $phone, array $payload, ?string $preview, ?MessageTemplate $template, ?Contact $contact, string $origin = 'agent', array $extra = [], bool $queue = false): Message
    {
        $workspaceId = $contact?->workspace_id ?? $user?->workspace_id ?? $phone->account->workspace_id;
        $waId = Contact::normalizeWaId($payload['to']);

        $contact ??= Contact::firstOrCreate(
            ['workspace_id' => $workspaceId, 'wa_id' => $waId],
            ['phone' => '+'.$waId, 'source' => 'outbound', 'created_by' => $user?->id],
        );

        return Message::create([
            'workspace_id' => $workspaceId,
            'phone_number_id' => $phone->id,
            'contact_id' => $contact->id,
            'message_template_id' => $template?->id,
            'user_id' => $user?->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'origin' => $origin,
            'type' => $payload['type'] ?? 'text',
            'status' => $queue ? 'queued' : 'pending',
            'to' => $waId,
            'from' => $phone->normalizedNumber(),
            'context_wamid' => $payload['context']['message_id'] ?? null,
            'preview' => $preview,
            'payload' => $payload,
        ] + $extra);
    }

    /** Perform the Graph call for a recorded message and store the outcome. */
    public function deliver(Message $message): Message
    {
        $phone = $message->phoneNumber()->with('account')->firstOrFail();
        $contact = $message->contact;
        $payload = $message->payload;
        $template = $message->template;
        $waId = $message->to;

        try {
            $response = $this->messaging->for($phone)->send($phone->phone_number_id, $payload);

            $message->forceFill([
                'wamid' => $response['messages'][0]['id'] ?? null,
                'status' => 'accepted',
                'response' => $response,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            if ($returnedWaId = $response['contacts'][0]['wa_id'] ?? null) {
                $returnedWaId = trim($returnedWaId);
                if ($returnedWaId !== $waId) {
                    $contact = Contact::firstOrCreate(['workspace_id' => $message->workspace_id, 'wa_id' => $returnedWaId]);
                    $message->update(['contact_id' => $contact->id, 'to' => $returnedWaId]);
                }
            }

            if ($contact) {
                $contact->update(['last_outbound_at' => now()]);
                $contact->touchConversation($message);

                if ($template && config('whatsapp.conversation.template_opens_window')) {
                    $contact->openWindow(Contact::OPENED_BY_TEMPLATE);
                }
            }
        } catch (GraphApiException $e) {
            $message->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error_code' => $e->graphCode,
                'error_title' => $e->userTitle ?? $e->type,
                'error_message' => $e->displayMessage(),
                'error_data' => $e->toArray(),
                'response' => $e->raw,
            ])->save();

            $contact?->touchConversation($message);

            throw $e;
        }

        return $message;
    }
}
