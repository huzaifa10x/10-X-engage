<?php

namespace App\Services\Meta;

use App\Models\WhatsAppAccount;

/**
 * WhatsApp Business Account + phone number management.
 * Cloud API collection: "Get Started", "WhatsApp Business Accounts", "Registration",
 * "Phone Numbers", "Webhook Subscriptions".
 */
class WabaService
{
    public function __construct(protected GraphClient $client) {}

    public function for(WhatsAppAccount $account): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withToken($account->apiToken());

        return $clone;
    }

    public function withToken(string $token): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withToken($token);

        return $clone;
    }

    /** "Get WABA" — GET /{{WABA-ID}} */
    public function getWaba(string $wabaId, ?array $fields = null): array
    {
        $fields ??= array_merge(
            ['id', 'name', 'currency', 'timezone_id', 'message_template_namespace', 'account_review_status'],
            // Only Business Solution Providers may read these (Graph error code 10 otherwise)
            config('whatsapp.partner.type') === 'solution_partner' ? ['owner_business_info', 'primary_funding_id'] : [],
        );

        return $this->client->get($wabaId, ['fields' => implode(',', $fields)]);
    }

    /** "Subscribe to your WABA" — POST /{{WABA-ID}}/subscribed_apps  =>  { "success": true } */
    public function subscribeApp(string $wabaId, ?string $overrideCallbackUri = null, ?string $verifyToken = null): array
    {
        $body = [];

        // "Override Callback URL" variant: include override_callback_uri + verify_token as JSON payload.
        if ($overrideCallbackUri) {
            $body = ['override_callback_uri' => $overrideCallbackUri, 'verify_token' => $verifyToken];
        }

        return $this->client->post("{$wabaId}/subscribed_apps", $body);
    }

    /** "Get All Subscriptions for a WABA" — GET /{{WABA-ID}}/subscribed_apps */
    public function subscriptions(string $wabaId): array
    {
        return $this->client->get("{$wabaId}/subscribed_apps");
    }

    /** "Unsubscribe from a WABA" — DELETE /{{WABA-ID}}/subscribed_apps */
    public function unsubscribeApp(string $wabaId): array
    {
        return $this->client->delete("{$wabaId}/subscribed_apps");
    }

    /** "Get Phone Numbers" — GET /{{WABA-ID}}/phone_numbers */
    public function phoneNumbers(string $wabaId): array
    {
        $fields = [
            'id', 'display_phone_number', 'verified_name', 'quality_rating', 'name_status', 'new_name_status',
            'code_verification_status', 'account_mode', 'messaging_limit_tier', 'platform_type',
            'is_official_business_account', 'status',
        ];

        return $this->client->get("{$wabaId}/phone_numbers", ['fields' => implode(',', $fields)]);
    }

    /** "Get Phone Number By ID" — GET /{{Phone-Number-ID}} */
    public function phoneNumber(string $phoneNumberId): array
    {
        $fields = [
            'id', 'display_phone_number', 'verified_name', 'quality_rating', 'name_status', 'new_name_status',
            'code_verification_status', 'account_mode', 'messaging_limit_tier', 'platform_type',
            'is_official_business_account', 'status',
        ];

        return $this->client->get($phoneNumberId, ['fields' => implode(',', $fields)]);
    }

    /**
     * "Register Phone Number" — POST /{{Phone-Number-ID}}/register
     * { "messaging_product": "whatsapp", "pin": "6-digit-pin" }
     * Registers the number AND sets the two-step verification PIN in one call.
     */
    public function registerPhone(string $phoneNumberId, string $pin): array
    {
        return $this->client->post("{$phoneNumberId}/register", [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);
    }

    /** "Deregister Phone" — POST /{{Phone-Number-ID}}/deregister */
    public function deregisterPhone(string $phoneNumberId): array
    {
        return $this->client->post("{$phoneNumberId}/deregister");
    }

    /**
     * "Request Verification Code" — POST /{{Phone-Number-ID}}/request_code
     * { "code_method": "SMS"|"VOICE", "locale": "en_US" }
     */
    public function requestVerificationCode(string $phoneNumberId, string $method = 'SMS', string $locale = 'en_US'): array
    {
        return $this->client->post("{$phoneNumberId}/request_code", [
            'code_method' => strtoupper($method),
            'locale' => $locale,
        ]);
    }

    /** "Verify Code" — POST /{{Phone-Number-ID}}/verify_code { "code": "123456" } */
    public function verifyCode(string $phoneNumberId, string $code): array
    {
        return $this->client->post("{$phoneNumberId}/verify_code", ['code' => $code]);
    }

    /** "Set Two-Step Verification Code" — POST /{{Phone-Number-ID}} { "pin": "123456" } */
    public function setTwoStepPin(string $phoneNumberId, string $pin): array
    {
        return $this->client->post($phoneNumberId, ['pin' => $pin]);
    }
}
