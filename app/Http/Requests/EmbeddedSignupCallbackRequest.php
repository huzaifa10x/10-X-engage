<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Body posted by the browser after FB.login() + the WA_EMBEDDED_SIGNUP message event. */
class EmbeddedSignupCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:2048'],
            'event' => ['nullable', 'string', 'max:64'],
            'waba_id' => ['nullable', 'string', 'max:64'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'business_id' => ['nullable', 'string', 'max:64'],
            'waba_ids' => ['nullable', 'array'],
            'waba_ids.*' => ['string', 'max:64'],
        ];
    }
}
