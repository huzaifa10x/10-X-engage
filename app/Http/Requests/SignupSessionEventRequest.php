<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** CANCEL / ERROR message events from the Embedded Signup popup (session logging). */
class SignupSessionEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:64'],
            'current_step' => ['nullable', 'string', 'max:64'],
            'error_code' => ['nullable', 'string', 'max:64'],
            'error_message' => ['nullable', 'string', 'max:1000'],
            'session_id' => ['nullable', 'string', 'max:128'],
            'timestamp' => ['nullable'],
            'data' => ['nullable', 'array'],
        ];
    }
}
