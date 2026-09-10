<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Converts the template builder form into the exact POST /{{WABA-ID}}/message_templates body:
 *
 * { "name", "language", "category", "components": [ HEADER?, BODY, FOOTER?, BUTTONS? ] }
 */
class TemplatePayloadBuilder
{
    /**
     * @param  array{
     *   name:string, language:string, category:string,
     *   header?: array{format?:string, text?:string, example?:string, handle?:string},
     *   body: array{text?:string, examples?:array, add_security_recommendation?:bool},
     *   footer?: array{text?:string, code_expiration_minutes?:int},
     *   buttons?: list<array>
     * }  $form
     */
    public static function build(array $form): array
    {
        $category = strtoupper($form['category']);

        $payload = [
            'name' => $form['name'],
            'language' => $form['language'],
            'category' => $category,
            'components' => [],
        ];

        if ($category === 'AUTHENTICATION') {
            $payload['components'] = self::authenticationComponents($form);

            return $payload;
        }

        // HEADER
        $header = $form['header'] ?? [];
        $format = strtoupper($header['format'] ?? 'NONE');

        if ($format === 'TEXT') {
            $text = trim((string) ($header['text'] ?? ''));
            if ($text === '') {
                throw new InvalidArgumentException('Header text is required for a TEXT header.');
            }
            $component = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $text];
            if (preg_match('/\{\{\d+\}\}/', $text)) {
                $component['example'] = ['header_text' => [(string) ($header['example'] ?? '')]];
            }
            $payload['components'][] = $component;
        } elseif (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            if (empty($header['handle'])) {
                throw new InvalidArgumentException("An uploaded sample file (header_handle) is required for a {$format} header.");
            }
            $payload['components'][] = [
                'type' => 'HEADER',
                'format' => $format,
                'example' => ['header_handle' => [$header['handle']]],
            ];
        } elseif ($format === 'LOCATION') {
            $payload['components'][] = ['type' => 'HEADER', 'format' => 'LOCATION'];
        }

        // BODY (required)
        $bodyText = trim((string) ($form['body']['text'] ?? ''));
        if ($bodyText === '') {
            throw new InvalidArgumentException('Body text is required.');
        }
        $body = ['type' => 'BODY', 'text' => $bodyText];
        $variables = preg_match_all('/\{\{(\d+)\}\}/', $bodyText, $m);
        if ($variables > 0) {
            $examples = array_values($form['body']['examples'] ?? []);
            if (count(array_filter($examples, fn ($e) => trim((string) $e) !== '')) < $variables) {
                throw new InvalidArgumentException("Provide an example value for each of the {$variables} body variable(s).");
            }
            $body['example'] = ['body_text' => [array_map('strval', array_slice($examples, 0, $variables))]];
        }
        $payload['components'][] = $body;

        // FOOTER
        $footer = trim((string) ($form['footer']['text'] ?? ''));
        if ($footer !== '') {
            $payload['components'][] = ['type' => 'FOOTER', 'text' => $footer];
        }

        // BUTTONS
        $buttons = self::buttons($form['buttons'] ?? []);
        if ($buttons) {
            $payload['components'][] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return $payload;
    }

    /**
     * "Create authentication template w/ OTP copy code button / one-tap autofill button"
     */
    protected static function authenticationComponents(array $form): array
    {
        $components = [
            ['type' => 'BODY', 'add_security_recommendation' => (bool) ($form['body']['add_security_recommendation'] ?? true)],
        ];

        $expiry = (int) ($form['footer']['code_expiration_minutes'] ?? 0);
        if ($expiry > 0) {
            $components[] = ['type' => 'FOOTER', 'code_expiration_minutes' => min($expiry, 90)];
        }

        $otp = $form['buttons'][0] ?? [];
        $otpType = strtoupper($otp['otp_type'] ?? 'COPY_CODE');

        $button = ['type' => 'OTP', 'otp_type' => $otpType, 'text' => $otp['text'] ?? 'Copy Code'];
        if ($otpType === 'ONE_TAP') {
            $button['autofill_text'] = $otp['autofill_text'] ?? 'Autofill';
            $button['package_name'] = $otp['package_name'] ?? '';
            $button['signature_hash'] = $otp['signature_hash'] ?? '';
        }

        $components[] = ['type' => 'BUTTONS', 'buttons' => [$button]];

        return $components;
    }

    protected static function buttons(array $buttons): array
    {
        $out = [];

        foreach ($buttons as $b) {
            $type = strtoupper($b['type'] ?? '');
            $text = trim((string) ($b['text'] ?? ''));

            switch ($type) {
                case 'QUICK_REPLY':
                    if ($text === '') {
                        throw new InvalidArgumentException('Quick reply buttons need a label.');
                    }
                    $out[] = ['type' => 'QUICK_REPLY', 'text' => mb_substr($text, 0, 25)];
                    break;

                case 'URL':
                    $url = trim((string) ($b['url'] ?? ''));
                    if ($text === '' || $url === '') {
                        throw new InvalidArgumentException('URL buttons need a label and a URL.');
                    }
                    $button = ['type' => 'URL', 'text' => mb_substr($text, 0, 25), 'url' => $url];
                    if (str_contains($url, '{{1}}')) {
                        $button['example'] = [(string) ($b['example'] ?? '')];
                    }
                    $out[] = $button;
                    break;

                case 'PHONE_NUMBER':
                    $phone = preg_replace('/[^\d+]/', '', (string) ($b['phone_number'] ?? ''));
                    if ($text === '' || $phone === '') {
                        throw new InvalidArgumentException('Phone number buttons need a label and a phone number.');
                    }
                    $out[] = ['type' => 'PHONE_NUMBER', 'text' => mb_substr($text, 0, 25), 'phone_number' => $phone];
                    break;

                case 'COPY_CODE':
                    $out[] = ['type' => 'COPY_CODE', 'example' => (string) ($b['example'] ?? 'CODE123')];
                    break;
            }
        }

        if (count($out) > 10) {
            throw new InvalidArgumentException('A template can have at most 10 buttons.');
        }

        return $out;
    }

    /** Human readable preview of the components (used in the UI / message log). */
    public static function summarize(array $components): string
    {
        foreach ($components as $c) {
            if (strtoupper($c['type'] ?? '') === 'BODY' && ! empty($c['text'])) {
                return $c['text'];
            }
        }

        return '';
    }
}
