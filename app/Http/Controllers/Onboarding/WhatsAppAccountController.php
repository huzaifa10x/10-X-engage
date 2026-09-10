<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\SyncWhatsAppAccount;
use App\Exceptions\GraphApiException;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Services\Meta\EmbeddedSignupService;
use App\Services\Meta\WabaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = $request->user()->workspace->whatsappAccounts()
            ->with('phoneNumbers')
            ->withCount('templates')
            ->latest()
            ->get()
            ->map(fn (WhatsAppAccount $a) => $this->summary($a));

        return Inertia::render('accounts/index', ['accounts' => $accounts]);
    }

    public function show(Request $request, WhatsAppAccount $account): Response
    {
        Gate::authorize('view', $account);

        $account->load('phoneNumbers');

        return Inertia::render('accounts/show', [
            'account' => $this->summary($account) + [
                'currency' => $account->currency,
                'timezone_id' => $account->timezone_id,
                'message_template_namespace' => $account->message_template_namespace,
                'business_id' => $account->business_id,
                'owner_business_name' => $account->owner_business_name,
                'account_review_status' => $account->account_review_status,
                'ban_state' => $account->ban_state,
                'primary_funding_id' => $account->primary_funding_id,
                'token_type' => $account->token_type,
                'token_scopes' => $account->token_scopes,
                'token_expires_at' => $account->token_expires_at?->toIso8601String(),
                'has_token' => $account->hasValidToken(),
                'webhook_subscribed_at' => $account->webhook_subscribed_at?->toIso8601String(),
                'system_user_assigned_at' => $account->system_user_assigned_at?->toIso8601String(),
                'credit_allocation_config_id' => $account->credit_allocation_config_id,
                'credit_line_shared_at' => $account->credit_line_shared_at?->toIso8601String(),
                'onboarded_at' => $account->onboarded_at?->toIso8601String(),
                'last_synced_at' => $account->last_synced_at?->toIso8601String(),
            ],
            'partner_type' => config('whatsapp.partner.type'),
            'webhook_url' => route('webhooks.whatsapp.handle'),
            'webhook_fields' => config('whatsapp.webhook.fields'),
        ]);
    }

    /** Re-fetch WABA + phone numbers from Graph. */
    public function sync(Request $request, WhatsAppAccount $account, SyncWhatsAppAccount $sync): RedirectResponse
    {
        Gate::authorize('update', $account);

        try {
            $sync($account);
        } catch (GraphApiException $e) {
            return back()->with('error', 'Sync failed: '.$e->displayMessage());
        }

        return back()->with('success', 'Account synced with Meta.');
    }

    /** POST /{{WABA-ID}}/subscribed_apps */
    public function subscribe(Request $request, WhatsAppAccount $account, WabaService $waba): RedirectResponse
    {
        Gate::authorize('update', $account);

        try {
            $result = $waba->for($account)->subscribeApp($account->waba_id);
        } catch (GraphApiException $e) {
            return back()->with('error', 'Subscription failed: '.$e->displayMessage());
        }

        if (($result['success'] ?? false) === true || ($result['success'] ?? null) === 'true') {
            $account->webhook_subscribed_at = now();
            $account->markStep(WhatsAppAccount::STEP_WEBHOOK_SUBSCRIBED);

            if ($account->phoneNumbers()->where('is_registered', true)->exists()) {
                $account->markStep(WhatsAppAccount::STEP_COMPLETED);
            }
        }

        return back()->with('success', 'App subscribed to WABA webhooks.');
    }

    /** GET /{{WABA-ID}}/subscribed_apps – confirm the subscription with Meta. */
    public function subscriptions(Request $request, WhatsAppAccount $account, WabaService $waba): RedirectResponse
    {
        Gate::authorize('view', $account);

        try {
            $result = $waba->for($account)->subscriptions($account->waba_id);
        } catch (GraphApiException $e) {
            return back()->with('error', $e->displayMessage());
        }

        $apps = collect($result['data'] ?? [])->map(fn ($d) => $d['whatsapp_business_api_data']['name'] ?? $d['whatsapp_business_api_data']['id'] ?? 'app');
        $subscribed = collect($result['data'] ?? [])->contains(fn ($d) => (string) ($d['whatsapp_business_api_data']['id'] ?? '') === (string) config('whatsapp.app.id'));

        $account->update(['webhook_subscribed_at' => $subscribed ? ($account->webhook_subscribed_at ?? now()) : null]);

        return back()->with($subscribed ? 'success' : 'warning', $apps->isEmpty()
            ? 'No apps are subscribed to this WABA.'
            : 'Subscribed apps: '.$apps->implode(', '));
    }

    /** DELETE /{{WABA-ID}}/subscribed_apps */
    public function unsubscribe(Request $request, WhatsAppAccount $account, WabaService $waba): RedirectResponse
    {
        Gate::authorize('update', $account);

        try {
            $waba->for($account)->unsubscribeApp($account->waba_id);
        } catch (GraphApiException $e) {
            return back()->with('error', $e->displayMessage());
        }

        $account->update(['webhook_subscribed_at' => null]);

        return back()->with('success', 'App unsubscribed from WABA webhooks.');
    }

    /** Solution partners: POST /{{Credit-Line-ID}}/whatsapp_credit_sharing_and_attach */
    public function shareCreditLine(Request $request, WhatsAppAccount $account, EmbeddedSignupService $signup): RedirectResponse
    {
        Gate::authorize('update', $account);

        if (! config('whatsapp.partner.credit_line_id')) {
            return back()->with('error', 'WHATSAPP_CREDIT_LINE_ID is not configured.');
        }

        try {
            $share = $signup->shareCreditLine($account->waba_id, $account->currency ?: config('whatsapp.partner.default_waba_currency'));
            $account->update([
                'credit_allocation_config_id' => $share['allocation_config_id'] ?? null,
                'credit_line_shared_at' => now(),
            ]);
        } catch (GraphApiException $e) {
            return back()->with('error', 'Credit line sharing failed: '.$e->displayMessage());
        }

        return back()->with('success', 'Line of credit shared with the WABA.');
    }

    /** Disconnect locally (token is discarded). */
    public function destroy(Request $request, WhatsAppAccount $account): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $account->update(['status' => WhatsAppAccount::STATUS_DISCONNECTED, 'access_token' => null]);

        return redirect()->route('accounts.index')->with('success', 'Account disconnected.');
    }

    protected function summary(WhatsAppAccount $a): array
    {
        return [
            'id' => $a->id,
            'waba_id' => $a->waba_id,
            'name' => $a->name,
            'status' => $a->status,
            'onboarding_step' => $a->onboarding_step,
            'webhook_subscribed' => $a->isWebhookSubscribed(),
            'templates_count' => $a->templates_count ?? null,
            'phone_numbers' => $a->phoneNumbers->map(fn ($p) => [
                'id' => $p->id,
                'phone_number_id' => $p->phone_number_id,
                'display_phone_number' => $p->display_phone_number,
                'verified_name' => $p->verified_name,
                'quality_rating' => $p->quality_rating,
                'name_status' => $p->name_status,
                'code_verification_status' => $p->code_verification_status,
                'account_mode' => $p->account_mode,
                'messaging_limit_tier' => $p->messaging_limit_tier,
                'platform_type' => $p->platform_type,
                'is_registered' => $p->is_registered,
                'registered_at' => $p->registered_at?->toIso8601String(),
                'is_default' => $p->is_default,
            ])->values(),
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
