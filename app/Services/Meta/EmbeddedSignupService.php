<?php

namespace App\Services\Meta;

use Carbon\CarbonImmutable;

/**
 * Embedded Signup collection — "WABAs" folder + the server-to-server code exchange documented
 * at developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-customers-as-a-tech-provider
 */
class EmbeddedSignupService
{
    public function __construct(protected GraphClient $client) {}

    /**
     * Step 1 (Tech Provider onboarding): exchange the token code returned by FB.login()
     * for a business integration system user access token ("business token").
     *
     * GET /oauth/access_token?client_id=&client_secret=&code=
     *
     * @return array{access_token:string, token_type?:string, expires_in?:int}
     */
    public function exchangeCode(string $code): array
    {
        $response = $this->client->withToken(null)->get('oauth/access_token', [
            'client_id' => config('whatsapp.app.id'),
            'client_secret' => config('whatsapp.app.secret'),
            'code' => $code,
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Token exchange succeeded but no access_token was returned.');
        }

        return $response;
    }

    /**
     * "Debug Token" — GET /debug_token?input_token={{OAuth-User-Token}}
     * Returns app_id, type, scopes, granular_scopes (target_ids = shared WABA IDs), expires_at, is_valid.
     */
    public function debugToken(string $inputToken): array
    {
        // debug_token must be called with an app token (app_id|app_secret) or a valid user token.
        $appToken = config('whatsapp.app.id').'|'.config('whatsapp.app.secret');

        $response = $this->client->withToken($appToken)->get('debug_token', ['input_token' => $inputToken]);

        return $response['data'] ?? [];
    }

    /**
     * Extract the WABA IDs shared with the app from debug_token granular_scopes
     * (scope whatsapp_business_management -> target_ids).
     *
     * @return list<string>
     */
    public function sharedWabaIdsFromDebug(array $debugData): array
    {
        $ids = [];
        foreach ($debugData['granular_scopes'] ?? [] as $scope) {
            if (in_array($scope['scope'] ?? '', ['whatsapp_business_management', 'whatsapp_business_messaging'], true)) {
                foreach ($scope['target_ids'] ?? [] as $id) {
                    $ids[] = (string) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function tokenExpiry(array $debugData): ?CarbonImmutable
    {
        $expiresAt = (int) ($debugData['expires_at'] ?? 0);

        // 0 means the token never expires (permanent system user / business token)
        return $expiresAt > 0 ? CarbonImmutable::createFromTimestamp($expiresAt) : null;
    }

    /**
     * "Shared WABAs" — GET /{{Business-ID}}/client_whatsapp_business_accounts
     * Requires the partner system user token.
     */
    public function sharedWabas(?string $businessId = null): array
    {
        $businessId ??= config('whatsapp.partner.business_id');

        return $this->partnerClient()->get("{$businessId}/client_whatsapp_business_accounts", [
            'fields' => 'id,name,currency,timezone_id,message_template_namespace,account_review_status,owner_business_info',
        ]);
    }

    /**
     * "Owned and Client WABAs" — GET /{{Business-ID}}/owned_whatsapp_business_accounts
     */
    public function ownedWabas(?string $businessId = null): array
    {
        $businessId ??= config('whatsapp.partner.business_id');

        return $this->partnerClient()->get("{$businessId}/owned_whatsapp_business_accounts", [
            'fields' => 'id,name,currency,timezone_id,message_template_namespace,account_review_status',
        ]);
    }

    /**
     * Step 2 – "Get IDs for the BSP's System Users" — GET /{{Business-ID}}/system_users
     */
    public function systemUsers(?string $businessId = null): array
    {
        $businessId ??= config('whatsapp.partner.business_id');

        return $this->partnerClient()->get("{$businessId}/system_users");
    }

    /**
     * Step 2 – "Add System User to WABA" — POST /{{Assigned-WABA-ID}}/assigned_users?user={{User-ID}}&tasks=['MANAGE']
     */
    public function addSystemUserToWaba(string $wabaId, string $systemUserId, array $tasks = ['MANAGE']): array
    {
        return $this->partnerClient()->post("{$wabaId}/assigned_users", [], [
            'user' => $systemUserId,
            'tasks' => json_encode($tasks),
        ]);
    }

    /**
     * Step 2 – "Fetch Assigned Users of WhatsApp Business Account" — GET /{{Assigned-WABA-ID}}/assigned_users?business={{Business-ID}}
     */
    public function assignedUsers(string $wabaId, ?string $businessId = null): array
    {
        $businessId ??= config('whatsapp.partner.business_id');

        return $this->partnerClient()->get("{$wabaId}/assigned_users", ['business' => $businessId]);
    }

    /**
     * Step 3 – "Get the ID for your Business' Line of Credit" — GET /{{Business-ID}}/extendedcredits?fields=id,legal_entity_name
     */
    public function extendedCredits(?string $businessId = null): array
    {
        $businessId ??= config('whatsapp.partner.business_id');

        return $this->partnerClient()->get("{$businessId}/extendedcredits", ['fields' => 'id,legal_entity_name']);
    }

    /**
     * Step 3 – "Attach Your Credit Line to the client's WABA"
     * POST /{{Credit-Line-ID}}/whatsapp_credit_sharing_and_attach?waba_id=&waba_currency=
     *
     * @return array{allocation_config_id:string, waba_id:string}
     */
    public function shareCreditLine(string $wabaId, string $currency, ?string $creditLineId = null): array
    {
        $creditLineId ??= config('whatsapp.partner.credit_line_id');

        return $this->partnerClient()->post("{$creditLineId}/whatsapp_credit_sharing_and_attach", [], [
            'waba_id' => $wabaId,
            'waba_currency' => $currency,
        ]);
    }

    /**
     * Step 3 – "Verify that the Line of Credit was Shared Correctly"
     * GET /{{Allocation-Config-ID}}?fields=receiving_credential{id}
     */
    public function verifyCreditShare(string $allocationConfigId): array
    {
        return $this->partnerClient()->get($allocationConfigId, ['fields' => 'receiving_credential{id}']);
    }

    /** "Credit Sharing Record ID" — GET /{{Allocation-Config-ID}} */
    public function creditShareStatus(string $allocationConfigId): array
    {
        return $this->partnerClient()->get($allocationConfigId, ['fields' => 'receiving_business,request_status']);
    }

    /** DELETE /{{Allocation-Config-ID}} — revoke credit sharing */
    public function revokeCreditShare(string $allocationConfigId): array
    {
        return $this->partnerClient()->delete($allocationConfigId);
    }

    protected function partnerClient(): GraphClient
    {
        return $this->client->withToken(config('whatsapp.partner.system_user_token'));
    }
}
