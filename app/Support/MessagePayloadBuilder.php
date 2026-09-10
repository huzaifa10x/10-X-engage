<?php

namespace App\Support;

use App\Models\MessageTemplate;
use InvalidArgumentException;

/**
 * Assembles Messages Objects exactly as documented in the Cloud API collection.
 * Every builder returns the full request body for POST /{{Phone-Number-ID}}/messages.
 */
class MessagePayloadBuilder
{
    protected array $base;

    public function __construct(string $to, ?string $replyToWamid = null)
    {
        $this->base = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ];

        // "Send Reply to ..." variants add a context object referencing the previous message.
        if ($replyToWamid) {
            $this->base['context'] = ['message_id' => $replyToWamid];
        }
    }

    /** "Send Text Message" / "Send Text Message with Preview URL" */
    public function text(string $body, bool $previewUrl = false): array
    {
        return $this->base + [
            'type' => 'text',
            'text' => ['preview_url' => $previewUrl, 'body' => $body],
        ];
    }

    /**
     * "Send Image/Audio/Document/Sticker/Video Message by ID / by URL"
     *
     * @param  'image'|'audio'|'document'|'sticker'|'video'  $type
     */
    public function media(string $type, ?string $mediaId = null, ?string $link = null, ?string $caption = null, ?string $filename = null): array
    {
        if (! in_array($type, ['image', 'audio', 'document', 'sticker', 'video'], true)) {
            throw new InvalidArgumentException("Unsupported media type [{$type}].");
        }

        if (! $mediaId && ! $link) {
            throw new InvalidArgumentException('A media id or link is required.');
        }

        $media = $mediaId ? ['id' => $mediaId] : ['link' => $link];

        // caption is not allowed for audio / sticker; filename only for documents.
        if ($caption !== null && $caption !== '' && in_array($type, ['image', 'document', 'video'], true)) {
            $media['caption'] = $caption;
        }
        if ($filename && $type === 'document') {
            $media['filename'] = $filename;
        }

        return $this->base + ['type' => $type, $type => $media];
    }

    /** "Send Location Message" */
    public function location(string|float $latitude, string|float $longitude, ?string $name = null, ?string $address = null): array
    {
        $location = ['latitude' => (string) $latitude, 'longitude' => (string) $longitude];
        if ($name) {
            $location['name'] = $name;
            if ($address) {
                $location['address'] = $address;
            }
        }

        return $this->base + ['type' => 'location', 'location' => $location];
    }

    /** "Send Reply Button" (interactive.type = button, max 3 buttons) */
    public function replyButtons(string $body, array $buttons, ?string $headerText = null, ?string $footer = null): array
    {
        $interactive = ['type' => 'button', 'body' => ['text' => $body]];
        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }
        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }
        $interactive['action'] = [
            'buttons' => array_map(fn ($b) => [
                'type' => 'reply',
                'reply' => ['id' => $b['id'], 'title' => $b['title']],
            ], array_slice($buttons, 0, 3)),
        ];

        return $this->base + ['type' => 'interactive', 'interactive' => $interactive];
    }

    /** "Send List Message" (interactive.type = list) */
    public function list(string $body, string $buttonText, array $sections, ?string $headerText = null, ?string $footer = null): array
    {
        $interactive = ['type' => 'list', 'body' => ['text' => $body]];
        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }
        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }
        $interactive['action'] = ['button' => $buttonText, 'sections' => $sections];

        return $this->base + ['type' => 'interactive', 'interactive' => $interactive];
    }

    /**
     * "Send Message Template Text / Media / Interactive"
     *
     * @param  array  $components  already-built components array (header/body/button parameters)
     */
    public function template(string $name, string $languageCode, array $components = []): array
    {
        $template = ['name' => $name, 'language' => ['code' => $languageCode]];
        if ($components) {
            $template['components'] = array_values($components);
        }

        return $this->base + ['type' => 'template', 'template' => $template];
    }

    /**
     * Build template components from a simple form structure:
     *  header: { type: text|image|video|document|location, text?, link?, id?, latitude?, longitude?, name?, address? }
     *  body:   ["value1", "value2", ...] (positional, matches {{1}}, {{2}} ... in the template body)
     *  buttons: [ { index: 0, sub_type: quick_reply|url|copy_code, payload?|text?|coupon_code? } ]
     */
    public static function templateComponents(MessageTemplate $template, array $input): array
    {
        $components = [];

        $header = $template->component('HEADER');
        if ($header) {
            $format = strtoupper($header['format'] ?? 'TEXT');
            $h = $input['header'] ?? [];

            if ($format === 'TEXT') {
                $needed = preg_match_all('/\{\{\d+\}\}/', $header['text'] ?? '');
                if ($needed > 0) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [['type' => 'text', 'text' => (string) ($h['text'] ?? '')]],
                    ];
                }
            } elseif (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                $key = strtolower($format);
                $media = ! empty($h['id']) ? ['id' => $h['id']] : ['link' => $h['link'] ?? ''];
                if ($format === 'DOCUMENT' && ! empty($h['filename'])) {
                    $media['filename'] = $h['filename'];
                }
                $components[] = ['type' => 'header', 'parameters' => [['type' => $key, $key => $media]]];
            } elseif ($format === 'LOCATION') {
                $components[] = ['type' => 'header', 'parameters' => [[
                    'type' => 'location',
                    'location' => [
                        'latitude' => (string) ($h['latitude'] ?? ''),
                        'longitude' => (string) ($h['longitude'] ?? ''),
                        'name' => $h['name'] ?? '',
                        'address' => $h['address'] ?? '',
                    ],
                ]]];
            }
        }

        $bodyValues = array_values($input['body'] ?? []);
        $bodyText = $template->bodyText() ?? '';
        $needed = preg_match_all('/\{\{\d+\}\}/', $bodyText);

        if ($template->category === 'AUTHENTICATION') {
            // Auth templates: body param is the OTP, url button (index 0) carries the same code.
            $code = (string) ($bodyValues[0] ?? '');
            $components[] = ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]];
            $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]];

            return $components;
        }

        if ($needed > 0) {
            if (count($bodyValues) < $needed) {
                throw new InvalidArgumentException("This template needs {$needed} body parameter(s).");
            }
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_slice($bodyValues, 0, $needed)),
            ];
        }

        foreach ($input['buttons'] ?? [] as $button) {
            $subType = strtolower($button['sub_type'] ?? '');
            $index = (string) ($button['index'] ?? 0);

            $parameters = match ($subType) {
                'quick_reply' => [['type' => 'payload', 'payload' => (string) ($button['payload'] ?? '')]],
                'url' => [['type' => 'text', 'text' => (string) ($button['text'] ?? '')]],
                'copy_code' => [['type' => 'coupon_code', 'coupon_code' => (string) ($button['coupon_code'] ?? '')]],
                default => [],
            };

            if ($parameters && ($parameters[0]['payload'] ?? $parameters[0]['text'] ?? $parameters[0]['coupon_code'] ?? '') !== '') {
                $components[] = ['type' => 'button', 'sub_type' => $subType, 'index' => $index, 'parameters' => $parameters];
            }
        }

        return $components;
    }
}
