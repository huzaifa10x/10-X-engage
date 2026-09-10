<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\CompleteEmbeddedSignup;
use App\Exceptions\GraphApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmbeddedSignupCallbackRequest;
use App\Http\Requests\SignupSessionEventRequest;
use App\Models\SignupSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmbeddedSignupController extends Controller
{
    /** Onboarding page: launches the Embedded Signup popup. */
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        $accounts = $workspace->whatsappAccounts()
            ->withCount('phoneNumbers')
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'waba_id' => $a->waba_id,
                'name' => $a->name,
                'status' => $a->status,
                'onboarding_step' => $a->onboarding_step,
                'phone_numbers_count' => $a->phone_numbers_count,
                'webhook_subscribed' => $a->isWebhookSubscribed(),
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        $sessions = $workspace->signupSessions()->latest()->limit(10)->get()->map(fn (SignupSession $s) => [
            'id' => $s->id,
            'event' => $s->event,
            'status' => $s->status,
            'waba_id' => $s->waba_id,
            'phone_number_id' => $s->phone_number_id,
            'current_step' => $s->current_step,
            'error_message' => $s->error_message ?: $s->failure_reason,
            'created_at' => $s->created_at?->toIso8601String(),
        ]);

        return Inertia::render('onboarding/index', [
            'accounts' => $accounts,
            'sessions' => $sessions,
            'signup' => [
                'app_id' => config('whatsapp.app.id'),
                'config_id' => config('whatsapp.embedded_signup.config_id'),
                'graph_version' => config('whatsapp.graph.version'),
                'version' => config('whatsapp.embedded_signup.version'),
                'features' => config('whatsapp.embedded_signup.features'),
                'feature_type' => config('whatsapp.embedded_signup.feature_type'),
                'configured' => (bool) (config('whatsapp.app.id') && config('whatsapp.app.secret') && config('whatsapp.embedded_signup.config_id')),
                'partner_type' => config('whatsapp.partner.type'),
                'webhook_url' => route('webhooks.whatsapp.handle'),
                'is_https' => $request->isSecure(),
            ],
        ]);
    }

    /**
     * Receives { code, waba_id, phone_number_id, business_id, event } from the browser and
     * runs the documented onboarding sequence. The code expires in 30 s, so this is synchronous.
     */
    public function callback(EmbeddedSignupCallbackRequest $request, CompleteEmbeddedSignup $complete): RedirectResponse|JsonResponse
    {
        try {
            $result = $complete($request->user(), $request->validated());
        } catch (GraphApiException $e) {
            return $this->failure($request, 'Meta rejected the onboarding request: '.$e->displayMessage());
        } catch (\Throwable $e) {
            return $this->failure($request, $e->getMessage());
        }

        $account = $result['account'];
        $flash = $result['warnings']
            ? ['warning' => 'Connected with warnings: '.implode(' ', $result['warnings'])]
            : ['success' => "WhatsApp Business Account {$account->waba_id} connected. Register the phone number to finish."];

        if ($request->wantsJson()) {
            return response()->json([
                'redirect' => route('accounts.show', $account),
                'account_id' => $account->id,
                'warnings' => $result['warnings'],
            ]);
        }

        return redirect()->route('accounts.show', $account)->with($flash);
    }

    /** Stores CANCEL / ERROR session-logging events for troubleshooting. */
    public function sessionEvent(SignupSessionEventRequest $request): JsonResponse
    {
        $user = $request->user();

        SignupSession::create([
            'workspace_id' => $user->workspace_id,
            'user_id' => $user->id,
            'event' => $request->input('event'),
            'current_step' => $request->input('current_step'),
            'error_code' => $request->input('error_code'),
            'error_message' => $request->input('error_message'),
            'meta_session_id' => $request->input('session_id'),
            'status' => 'received',
            'payload' => $request->input('data'),
        ]);

        return response()->json(['ok' => true]);
    }

    protected function failure(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->with('error', $message);
    }
}
