<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\RegisterPhoneNumber;
use App\Exceptions\GraphApiException;
use App\Http\Controllers\Controller;
use App\Models\PhoneNumber;
use App\Services\Meta\WabaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PhoneNumberController extends Controller
{
    /** POST /{{Phone-Number-ID}}/register with a 6-digit PIN. */
    public function register(Request $request, PhoneNumber $phone, RegisterPhoneNumber $register): RedirectResponse
    {
        Gate::authorize('update', $phone->account);

        $data = $request->validate([
            'pin' => ['required', 'digits:6'],
        ]);

        try {
            $register($phone, $data['pin']);
        } catch (GraphApiException $e) {
            return back()->with('error', 'Registration failed: '.$e->displayMessage());
        }

        return back()->with('success', "{$phone->display_phone_number} registered for Cloud API.");
    }

    /** POST /{{Phone-Number-ID}}/deregister */
    public function deregister(Request $request, PhoneNumber $phone, WabaService $waba): RedirectResponse
    {
        Gate::authorize('update', $phone->account);

        try {
            $waba->for($phone->account)->deregisterPhone($phone->phone_number_id);
        } catch (GraphApiException $e) {
            return back()->with('error', 'Deregistration failed: '.$e->displayMessage());
        }

        $phone->update(['is_registered' => false, 'registered_at' => null]);

        return back()->with('success', 'Phone number deregistered.');
    }

    /** POST /{{Phone-Number-ID}}/request_code { code_method, locale } */
    public function requestCode(Request $request, PhoneNumber $phone, WabaService $waba): RedirectResponse
    {
        Gate::authorize('update', $phone->account);

        $data = $request->validate([
            'code_method' => ['required', 'in:SMS,VOICE'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $waba->for($phone->account)->requestVerificationCode($phone->phone_number_id, $data['code_method'], $data['locale'] ?? 'en_US');
        } catch (GraphApiException $e) {
            return back()->with('error', 'Could not request a code: '.$e->displayMessage());
        }

        $phone->update(['verification_code_requested_at' => now()]);

        return back()->with('success', "Verification code sent via {$data['code_method']}.");
    }

    /** POST /{{Phone-Number-ID}}/verify_code { code } */
    public function verifyCode(Request $request, PhoneNumber $phone, WabaService $waba): RedirectResponse
    {
        Gate::authorize('update', $phone->account);

        $data = $request->validate(['code' => ['required', 'digits_between:4,8']]);

        try {
            $waba->for($phone->account)->verifyCode($phone->phone_number_id, $data['code']);
        } catch (GraphApiException $e) {
            return back()->with('error', 'Verification failed: '.$e->displayMessage());
        }

        $phone->update(['code_verification_status' => 'VERIFIED']);

        return back()->with('success', 'Phone number ownership verified.');
    }

    /** POST /{{Phone-Number-ID}} { pin } — change the two-step PIN. */
    public function updatePin(Request $request, PhoneNumber $phone, WabaService $waba): RedirectResponse
    {
        Gate::authorize('update', $phone->account);

        $data = $request->validate(['pin' => ['required', 'digits:6']]);

        try {
            $waba->for($phone->account)->setTwoStepPin($phone->phone_number_id, $data['pin']);
        } catch (GraphApiException $e) {
            return back()->with('error', 'PIN update failed: '.$e->displayMessage());
        }

        $phone->forceFill(['two_step_pin' => $data['pin']])->save();

        return back()->with('success', 'Two-step verification PIN updated.');
    }

    public function setDefault(Request $request, PhoneNumber $phone): RedirectResponse
    {
        Gate::authorize('update', $phone->account);

        $phone->account->phoneNumbers()->update(['is_default' => false]);
        $phone->update(['is_default' => true]);

        return back()->with('success', 'Default sender updated.');
    }
}
