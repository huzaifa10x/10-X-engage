<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Inbox composer: text, media (already uploaded / link) or template. */
class InboxSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'phone_number_id' => ['nullable', 'integer', 'exists:phone_numbers,id'],
            'reply_to' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['text', 'template', 'image', 'video', 'document', 'audio'])],

            'text' => ['required_if:type,text', 'array'],
            'text.body' => ['required_if:type,text', 'nullable', 'string', 'max:4096'],
            'text.preview_url' => ['nullable', 'boolean'],

            'media' => ['required_if:type,image,video,document,audio', 'array'],
            'media.source' => ['nullable', Rule::in(['link', 'id'])],
            'media.link' => ['nullable', 'url', 'max:2048'],
            'media.id' => ['nullable', 'string', 'max:64'],
            'media.caption' => ['nullable', 'string', 'max:1024'],
            'media.filename' => ['nullable', 'string', 'max:240'],

            'template' => ['required_if:type,template', 'array'],
            'template.id' => ['required_if:type,template', 'nullable', 'integer', 'exists:message_templates,id'],
            'template.header' => ['nullable', 'array'],
            'template.body' => ['nullable', 'array'],
            'template.body.*' => ['nullable', 'string', 'max:1024'],
            'template.buttons' => ['nullable', 'array'],
            'template.buttons.*.index' => ['nullable', 'integer', 'min:0', 'max:9'],
            'template.buttons.*.sub_type' => ['nullable', Rule::in(['quick_reply', 'url', 'copy_code'])],
            'template.buttons.*.payload' => ['nullable', 'string', 'max:256'],
            'template.buttons.*.text' => ['nullable', 'string', 'max:512'],
            'template.buttons.*.coupon_code' => ['nullable', 'string', 'max:15'],
        ];
    }
}
