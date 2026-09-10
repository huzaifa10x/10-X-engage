<?php

namespace App\Actions\Onboarding;

use App\Models\PhoneNumber;
use App\Models\WhatsAppAccount;
use App\Services\Meta\WabaService;

/**
 * POST /{{Phone-Number-ID}}/register { messaging_product: whatsapp, pin }
 * Registers the number for Cloud API and sets its two-step verification PIN.
 * Embedded Signup numbers must be registered within 14 days of completing the flow.
 */
class RegisterPhoneNumber
{
    public function __construct(protected WabaService $waba) {}

    public function __invoke(PhoneNumber $phone, string $pin): PhoneNumber
    {
        $account = $phone->account;

        $result = $this->waba->for($account)->registerPhone($phone->phone_number_id, $pin);

        if (($result['success'] ?? false) === true || ($result['success'] ?? null) === 'true') {
            $phone->forceFill([
                'is_registered' => true,
                'registered_at' => now(),
                'two_step_pin' => $pin,
            ])->save();

            $account->markStep(WhatsAppAccount::STEP_PHONE_REGISTERED);

            if ($account->isWebhookSubscribed()) {
                $account->markStep(WhatsAppAccount::STEP_COMPLETED);
            }
        }

        return $phone->refresh();
    }
}
