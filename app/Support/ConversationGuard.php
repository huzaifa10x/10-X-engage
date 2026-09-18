<?php

namespace App\Support;

use App\Models\Contact;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the product rules for outbound messages:
 *  - the recipient must already exist as a Contact in the workspace;
 *  - free-form (non-template) messages are only allowed while the contact's 24-hour window is open.
 */
class ConversationGuard
{
    public const WINDOW_CLOSED_MESSAGE = '24-hour window has been closed. Send an approved template to open the conversation.';

    public const NO_HISTORY_MESSAGE = 'This contact has not messaged you yet. The first message must be an approved template.';

    public static function resolveContact(int $workspaceId, string $to): Contact
    {
        $waId = Contact::normalizeWaId($to);

        $contact = Contact::where('workspace_id', $workspaceId)->where('wa_id', $waId)->first();

        if (! $contact) {
            throw ValidationException::withMessages([
                'to' => "No contact with number +{$waId}. Create the contact first, then start the conversation with a template.",
            ]);
        }

        return $contact;
    }

    public static function assertCanSend(Contact $contact, string $type, string $field = 'type'): void
    {
        if ($type === 'template') {
            return;
        }

        if ($contact->isWindowOpen()) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $contact->last_message_at ? self::WINDOW_CLOSED_MESSAGE : self::NO_HISTORY_MESSAGE,
        ]);
    }
}
