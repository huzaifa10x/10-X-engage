<?php

namespace App\Services;

use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Suppression;
use App\Models\Workspace;

/**
 * Opt-in / opt-out handling with an append-only ledger. Keyword matching is whole-message,
 * never substring ("please don't stop my class" must not unsubscribe anyone).
 */
class ConsentService
{
    public const SCOPES = ['marketing', 'transactional'];

    public function optIn(Contact $contact, string $source, array $scope = self::SCOPES, ?string $keyword = null, ?string $consentText = null, ?int $userId = null): void
    {
        $contact->forceFill([
            'opt_in_status' => Contact::OPT_IN,
            'opt_in_scope' => array_values(array_unique($scope)),
            'opted_at' => now(),
        ])->save();

        Suppression::where('workspace_id', $contact->workspace_id)->where('wa_id', $contact->wa_id)->delete();

        $this->log($contact, 'opt_in', $source, $scope, $keyword, $consentText, $userId);
    }

    public function optOut(Contact $contact, string $source, ?string $keyword = null, ?int $userId = null, string $reason = 'opt_out'): void
    {
        $contact->forceFill([
            'opt_in_status' => Contact::OPT_OUT,
            'opt_in_scope' => [],
            'opted_at' => now(),
        ])->save();

        Suppression::updateOrCreate(
            ['workspace_id' => $contact->workspace_id, 'wa_id' => $contact->wa_id],
            ['reason' => $reason, 'user_id' => $userId],
        );

        $this->log($contact, 'opt_out', $source, [], $keyword, null, $userId);
    }

    /** Reset to unknown (admin action) – suppression is lifted but no consent is claimed. */
    public function clear(Contact $contact, ?int $userId = null): void
    {
        $contact->forceFill(['opt_in_status' => Contact::OPT_UNKNOWN, 'opt_in_scope' => null, 'opted_at' => null])->save();
        Suppression::where('workspace_id', $contact->workspace_id)->where('wa_id', $contact->wa_id)->delete();
    }

    /**
     * Inspect an inbound text; returns 'opt_in' | 'opt_out' | null and applies the change.
     */
    public function interceptKeyword(Workspace $workspace, Contact $contact, ?string $text): ?string
    {
        $normalized = self::normalize((string) $text);
        if ($normalized === '') {
            return null;
        }

        $optOut = array_map([self::class, 'normalize'], (array) $workspace->setting('opt_out_keywords', []));
        $optIn = array_map([self::class, 'normalize'], (array) $workspace->setting('opt_in_keywords', []));

        if (in_array($normalized, $optOut, true)) {
            $this->optOut($contact, 'keyword', $text);

            return 'opt_out';
        }
        if (in_array($normalized, $optIn, true)) {
            $this->optIn($contact, 'keyword', self::SCOPES, $text);

            return 'opt_in';
        }

        return null;
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        // strip punctuation / emoji / symbols, keep letters (any script), digits and spaces
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    protected function log(Contact $contact, string $action, string $source, array $scope, ?string $keyword, ?string $consentText, ?int $userId): void
    {
        ConsentEvent::create([
            'workspace_id' => $contact->workspace_id,
            'contact_id' => $contact->id,
            'wa_id' => $contact->wa_id,
            'action' => $action,
            'source' => $source,
            'scope' => $scope,
            'keyword' => $keyword ? mb_substr($keyword, 0, 64) : null,
            'consent_text' => $consentText,
            'user_id' => $userId,
        ]);
    }
}
