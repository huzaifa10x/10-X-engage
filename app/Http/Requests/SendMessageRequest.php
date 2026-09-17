<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The composer submits every section of the form; keep only the one that matches the
     * selected type so rules for hidden sections (e.g. interactive.buttons.*.title) can't fail.
     */
    protected function prepareForValidation(): void
    {
        $type = $this->input('type');

        $keep = match ($type) {
            'text' => ['text'],
            'image', 'video', 'document', 'audio', 'sticker' => ['media'],
            'location' => ['location'],
            'template' => ['template'],
            'interactive' => ['interactive'],
            default => [],
        };

        foreach (['text', 'media', 'location', 'template', 'interactive'] as $section) {
            if (! in_array($section, $keep, true)) {
                $this->request->remove($section);
            }
        }
    }

    public function rules(): array
    {
        return [
            'phone_number_id' => ['required', 'integer', 'exists:phone_numbers,id'],
            'to' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'type' => ['required', Rule::in(['text', 'template', 'image', 'video', 'document', 'audio', 'sticker', 'location', 'interactive'])],
            'reply_to' => ['nullable', 'string', 'max:255'],

            // text
            'text.body' => ['required_if:type,text', 'nullable', 'string', 'max:4096'],
            'text.preview_url' => ['nullable', 'boolean'],

            // media (by id or link)
            'media.source' => ['nullable', Rule::in(['link', 'id'])],
            'media.link' => ['nullable', 'url', 'max:2048'],
            'media.id' => ['nullable', 'string', 'max:128'],
            'media.caption' => ['nullable', 'string', 'max:1024'],
            'media.filename' => ['nullable', 'string', 'max:255'],

            // location
            'location.latitude' => ['required_if:type,location', 'nullable', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required_if:type,location', 'nullable', 'numeric', 'between:-180,180'],
            'location.name' => ['nullable', 'string', 'max:255'],
            'location.address' => ['nullable', 'string', 'max:255'],

            // template
            'template.id' => ['required_if:type,template', 'nullable', 'integer', 'exists:message_templates,id'],
            'template.header' => ['nullable', 'array'],
            'template.body' => ['nullable', 'array'],
            'template.body.*' => ['nullable', 'string', 'max:1024'],
            'template.buttons' => ['nullable', 'array'],

            // interactive reply buttons
            'interactive.kind' => ['nullable', Rule::in(['button', 'list'])],
            'interactive.header' => ['nullable', 'string', 'max:60'],
            'interactive.body' => ['required_if:type,interactive', 'nullable', 'string', 'max:1024'],
            'interactive.footer' => ['nullable', 'string', 'max:60'],
            'interactive.buttons' => ['nullable', 'array', 'max:3'],
            'interactive.buttons.*.id' => ['required_if:type,interactive', 'nullable', 'string', 'max:256'],
            'interactive.buttons.*.title' => ['required_if:type,interactive', 'nullable', 'string', 'max:20'],
            'interactive.button_text' => ['nullable', 'string', 'max:20'],
            'interactive.sections' => ['nullable', 'array', 'max:10'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (in_array($this->input('type'), ['image', 'video', 'document', 'audio', 'sticker'], true)) {
                if (! $this->input('media.link') && ! $this->input('media.id')) {
                    $v->errors()->add('media.link', 'Provide a public media URL or upload a file first.');
                }
            }
        });
    }
}
