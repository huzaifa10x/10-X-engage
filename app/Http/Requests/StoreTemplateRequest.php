<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'whatsapp_account_id' => ['required', 'integer', 'exists:whatsapp_accounts,id'],
            // Meta: lowercase letters, numbers and underscores only, max 512
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:16'],
            'category' => ['required', Rule::in(config('whatsapp.templates.categories'))],

            'header.format' => ['nullable', Rule::in(['NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'])],
            'header.text' => ['nullable', 'string', 'max:60'],
            'header.example' => ['nullable', 'string', 'max:60'],
            'header.handle' => ['nullable', 'string'],

            'body.text' => ['required_unless:category,AUTHENTICATION', 'nullable', 'string', 'max:1024'],
            'body.examples' => ['nullable', 'array'],
            'body.examples.*' => ['nullable', 'string', 'max:255'],
            'body.add_security_recommendation' => ['nullable', 'boolean'],

            'footer.text' => ['nullable', 'string', 'max:60'],
            'footer.code_expiration_minutes' => ['nullable', 'integer', 'between:1,90'],

            'buttons' => ['nullable', 'array', 'max:10'],
            'buttons.*.type' => ['required_with:buttons', Rule::in(['QUICK_REPLY', 'URL', 'PHONE_NUMBER', 'COPY_CODE', 'OTP'])],
            'buttons.*.text' => ['nullable', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2000'],
            'buttons.*.example' => ['nullable', 'string', 'max:255'],
            'buttons.*.phone_number' => ['nullable', 'string', 'max:20'],
            'buttons.*.otp_type' => ['nullable', Rule::in(['COPY_CODE', 'ONE_TAP'])],
            'buttons.*.autofill_text' => ['nullable', 'string', 'max:25'],
            'buttons.*.package_name' => ['nullable', 'string', 'max:255'],
            'buttons.*.signature_hash' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Template names may only contain lowercase letters, numbers and underscores.',
        ];
    }
}
