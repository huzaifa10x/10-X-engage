<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $phone = (string) $this->input('phone', '');
        $this->merge([
            'wa_id' => Contact::normalizeWaId($phone),
            'phone' => $phone !== '' ? '+'.Contact::normalizeWaId($phone) : null,
            'tags' => collect($this->input('tags', []))->map(fn ($t) => trim((string) $t))->filter()->unique()->values()->all(),
        ]);
    }

    public function rules(): array
    {
        $contactId = $this->route('contact')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{6,15}$/'],
            'wa_id' => [
                'required',
                Rule::unique('contacts', 'wa_id')
                    ->where('workspace_id', $this->user()->workspace_id)
                    ->ignore($contactId),
            ],
            'email' => ['nullable', 'email', 'max:190'],
            'company' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'phone_number_id' => ['nullable', 'integer', 'exists:phone_numbers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter the number in international format, e.g. +971501234567.',
            'wa_id.unique' => 'A contact with this WhatsApp number already exists.',
        ];
    }
}
