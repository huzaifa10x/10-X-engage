<?php

namespace App\Actions\Onboarding;

use App\Exceptions\GraphApiException;
use App\Models\PhoneNumber;
use App\Models\WhatsAppAccount;
use App\Services\Meta\WabaService;

/**
 * Pull WABA details + phone numbers from Graph and mirror them locally.
 */
class SyncWhatsAppAccount
{
    public function __construct(protected WabaService $waba) {}

    public function __invoke(WhatsAppAccount $account): WhatsAppAccount
    {
        $api = $this->waba->for($account);

        try {
            $waba = $api->getWaba($account->waba_id);
        } catch (GraphApiException $e) {
            // Some fields (owner_business_info, primary_funding_id) need extra permissions – retry with the basics.
            $waba = $api->getWaba($account->waba_id, ['id', 'name', 'currency', 'timezone_id', 'message_template_namespace', 'account_review_status']);
        }

        $account->fill([
            'name' => $waba['name'] ?? $account->name,
            'currency' => $waba['currency'] ?? $account->currency,
            'timezone_id' => $waba['timezone_id'] ?? $account->timezone_id,
            'message_template_namespace' => $waba['message_template_namespace'] ?? $account->message_template_namespace,
            'account_review_status' => $waba['account_review_status'] ?? $account->account_review_status,
            'primary_funding_id' => $waba['primary_funding_id'] ?? $account->primary_funding_id,
            'business_id' => $waba['owner_business_info']['id'] ?? $account->business_id,
            'owner_business_name' => $waba['owner_business_info']['name'] ?? $account->owner_business_name,
            'last_synced_at' => now(),
        ]);
        $account->save();

        $this->syncPhoneNumbers($account, $api);

        return $account->refresh();
    }

    public function syncPhoneNumbers(WhatsAppAccount $account, ?WabaService $api = null): void
    {
        $api ??= $this->waba->for($account);
        $numbers = $api->phoneNumbers($account->waba_id)['data'] ?? [];

        $seen = [];
        foreach ($numbers as $n) {
            $seen[] = $n['id'];

            /** @var PhoneNumber $phone */
            $phone = PhoneNumber::firstOrNew(['phone_number_id' => (string) $n['id']]);
            $phone->whatsapp_account_id = $account->id;
            $phone->fill([
                'display_phone_number' => $n['display_phone_number'] ?? $phone->display_phone_number,
                'verified_name' => $n['verified_name'] ?? $phone->verified_name,
                'quality_rating' => $n['quality_rating'] ?? $phone->quality_rating,
                'name_status' => $n['name_status'] ?? $phone->name_status,
                'new_name_status' => $n['new_name_status'] ?? $phone->new_name_status,
                'code_verification_status' => $n['code_verification_status'] ?? $phone->code_verification_status,
                'account_mode' => $n['account_mode'] ?? $phone->account_mode,
                'messaging_limit_tier' => $n['messaging_limit_tier'] ?? $phone->messaging_limit_tier,
                'platform_type' => $n['platform_type'] ?? $phone->platform_type,
                'is_official_business_account' => (bool) ($n['is_official_business_account'] ?? false),
                'meta' => $n,
                'last_synced_at' => now(),
            ]);

            // Cloud API reports registration via platform_type CLOUD_API + status CONNECTED.
            if (($n['platform_type'] ?? null) === 'CLOUD_API' && ($n['status'] ?? null) === 'CONNECTED') {
                $phone->is_registered = true;
                $phone->registered_at ??= now();
            }

            $phone->save();
        }

        if (! $account->phoneNumbers()->where('is_default', true)->exists()) {
            $account->phoneNumbers()->orderBy('id')->first()?->update(['is_default' => true]);
        }
    }
}
