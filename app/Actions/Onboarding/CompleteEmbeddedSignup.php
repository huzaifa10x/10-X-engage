<?php

namespace App\Actions\Onboarding;

use App\Exceptions\GraphApiException;
use App\Models\SignupSession;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Meta\EmbeddedSignupService;
use App\Services\Meta\WabaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Executes the documented post-Embedded-Signup onboarding sequence:
 *
 *  1. Exchange the token code for a business token         (GET /oauth/access_token)
 *  2. Debug the token to confirm scopes + shared WABA IDs  (GET /debug_token)
 *  3. Fetch WABA details                                    (GET /{WABA-ID})
 *  4. Subscribe the app to the WABA's webhooks              (POST /{WABA-ID}/subscribed_apps)
 *  5. Fetch phone numbers                                   (GET /{WABA-ID}/phone_numbers)
 *  6. (optional) Assign partner system user                 (POST /{WABA-ID}/assigned_users)
 *  7. (solution partners) Share line of credit              (POST /{Credit-Line-ID}/whatsapp_credit_sharing_and_attach)
 *
 *  5b. Register the phone number for Cloud API             (POST /{Phone-Number-ID}/register)
 *      Runs automatically when whatsapp.onboarding.auto_register is true, using a generated
 *      6-digit two-step PIN. If Meta rejects it (number already has a PIN / not verified), the
 *      account page offers manual registration via RegisterPhoneNumber.
 */
class CompleteEmbeddedSignup
{
    public function __construct(
        protected EmbeddedSignupService $signup,
        protected WabaService $waba,
        protected SyncWhatsAppAccount $sync,
        protected RegisterPhoneNumber $register,
    ) {}

    /**
     * @param  array{code:string, waba_id?:string, phone_number_id?:string, business_id?:string, event?:string, waba_ids?:array}  $input
     * @return array{account: WhatsAppAccount, session: SignupSession, warnings: list<string>}
     */
    public function __invoke(User $user, array $input): array
    {
        $warnings = [];

        $session = SignupSession::create([
            'workspace_id' => $user->workspace_id,
            'user_id' => $user->id,
            'event' => $input['event'] ?? 'FINISH',
            'waba_id' => $input['waba_id'] ?? null,
            'phone_number_id' => $input['phone_number_id'] ?? null,
            'business_id' => $input['business_id'] ?? null,
            'status' => 'received',
            'payload' => collect($input)->except('code')->all(),
        ]);

        try {
            // 1. Exchange code -> business token (code TTL is 30s, so this runs synchronously)
            $token = $this->signup->exchangeCode($input['code']);
            $session->update(['status' => 'exchanged', 'code_exchanged_at' => now()]);

            // 2. Debug token: scopes + shared WABA IDs
            $debug = [];
            try {
                $debug = $this->signup->debugToken($token['access_token']);
            } catch (GraphApiException $e) {
                $warnings[] = 'Token debug failed: '.$e->displayMessage();
            }

            $wabaId = $input['waba_id'] ?? null;
            $sharedIds = $this->signup->sharedWabaIdsFromDebug($debug);

            if (! $wabaId) {
                $wabaId = $sharedIds[0] ?? throw new \RuntimeException(
                    'Embedded Signup did not return a WABA ID and none was found in the token scopes.'
                );
            }

            $account = DB::transaction(function () use ($user, $wabaId, $token, $debug, $input) {
                /** @var WhatsAppAccount $account */
                $account = WhatsAppAccount::firstOrNew(['waba_id' => (string) $wabaId]);

                if ($account->exists && $account->workspace_id !== $user->workspace_id) {
                    throw new \RuntimeException("WABA {$wabaId} is already connected to another workspace.");
                }

                $account->fill([
                    'workspace_id' => $user->workspace_id,
                    'business_id' => $input['business_id'] ?? $account->business_id,
                    'access_token' => $token['access_token'],
                    'token_type' => $debug['type'] ?? ($token['token_type'] ?? 'BUSINESS'),
                    'token_scopes' => $debug['scopes'] ?? null,
                    'token_expires_at' => $this->signup->tokenExpiry($debug),
                    'token_debugged_at' => $debug ? now() : null,
                    'status' => WhatsAppAccount::STATUS_PENDING_SETUP,
                    'onboarding_step' => WhatsAppAccount::STEP_TOKEN_EXCHANGED,
                    'meta' => array_merge($account->meta ?? [], [
                        'signup_event' => $input['event'] ?? 'FINISH',
                        'shared_waba_ids' => $this->signup->sharedWabaIdsFromDebug($debug),
                        'waba_ids' => $input['waba_ids'] ?? null,
                    ]),
                ]);
                $account->save();

                return $account;
            });

            // 3 + 5. WABA details and phone numbers
            try {
                ($this->sync)($account);
            } catch (GraphApiException $e) {
                $warnings[] = 'Could not sync WABA details: '.$e->displayMessage();
            }

            // 4. Subscribe app to webhooks on the customer's WABA
            try {
                $result = $this->waba->for($account)->subscribeApp($account->waba_id);
                if (($result['success'] ?? false) === true || ($result['success'] ?? null) === 'true') {
                    $account->webhook_subscribed_at = now();
                    $account->markStep(WhatsAppAccount::STEP_WEBHOOK_SUBSCRIBED);
                }
            } catch (GraphApiException $e) {
                $warnings[] = 'Webhook subscription failed: '.$e->displayMessage();
            }

            // 5b. Register the onboarded phone number(s) for Cloud API
            if (config('whatsapp.onboarding.auto_register')) {
                $warnings = array_merge($warnings, $this->autoRegisterPhones($account, $input['phone_number_id'] ?? null));
            }

            // 6. Assign our system user to the WABA (optional, partner-level token required)
            if (config('whatsapp.partner.system_user_id') && config('whatsapp.partner.system_user_token')) {
                try {
                    $this->signup->addSystemUserToWaba(
                        $account->waba_id,
                        config('whatsapp.partner.system_user_id'),
                        config('whatsapp.partner.system_user_tasks', ['MANAGE'])
                    );
                    $account->update(['system_user_assigned_at' => now()]);
                } catch (GraphApiException $e) {
                    $warnings[] = 'System user assignment failed: '.$e->displayMessage();
                }
            }

            // 7. Solution partners share their line of credit with the client WABA
            if (config('whatsapp.partner.type') === 'solution_partner' && config('whatsapp.partner.credit_line_id')) {
                try {
                    $share = $this->signup->shareCreditLine(
                        $account->waba_id,
                        $account->currency ?: config('whatsapp.partner.default_waba_currency')
                    );
                    $account->update([
                        'credit_allocation_config_id' => $share['allocation_config_id'] ?? null,
                        'credit_line_shared_at' => now(),
                    ]);
                } catch (GraphApiException $e) {
                    $warnings[] = 'Credit line sharing failed: '.$e->displayMessage();
                }
            }

            $session->update([
                'whatsapp_account_id' => $account->id,
                'status' => 'completed',
                'failure_reason' => $warnings ? implode(' | ', $warnings) : null,
            ]);

            return ['account' => $account->refresh(), 'session' => $session, 'warnings' => $warnings];
        } catch (\Throwable $e) {
            Log::error('Embedded signup onboarding failed', ['exception' => $e, 'session_id' => $session->id]);

            $session->update([
                'status' => 'failed',
                'failure_reason' => $e instanceof GraphApiException ? $e->displayMessage() : $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Registers the number returned by the signup popup (or every unregistered number on the WABA).
     * Cloud API requires POST /{Phone-Number-ID}/register before the number can send or receive.
     *
     * @return list<string> warnings
     */
    protected function autoRegisterPhones(WhatsAppAccount $account, ?string $phoneNumberId): array
    {
        $warnings = [];

        $phones = $account->phoneNumbers()
            ->where('is_registered', false)
            ->when($phoneNumberId, fn ($q) => $q->where('phone_number_id', $phoneNumberId))
            ->get();

        foreach ($phones as $phone) {
            if ($phone->code_verification_status && strtoupper($phone->code_verification_status) !== 'VERIFIED') {
                $warnings[] = "{$phone->display_phone_number}: not registered – ownership is {$phone->code_verification_status}. Verify the number, then register it from the account page.";
                continue;
            }

            // Reuse a PIN we already know for this number, otherwise generate one.
            $pin = $phone->two_step_pin ?: str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            try {
                ($this->register)($phone, $pin);
            } catch (GraphApiException $e) {
                $phone->forceFill(['two_step_pin' => null])->save();
                $warnings[] = "{$phone->display_phone_number}: automatic registration failed – {$e->displayMessage()} "
                    .'If two-step verification was previously enabled on this number, register it from the account page using that PIN.';
            }
        }

        return $warnings;
    }
}
