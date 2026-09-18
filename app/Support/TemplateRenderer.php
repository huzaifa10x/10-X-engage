<?php

namespace App\Support;

use App\Models\MessageTemplate;

/**
 * Renders a template with the parameters that were actually sent (from the Messages Object
 * `template.components` array), so the inbox shows "Hello Daniyal" instead of "Hello {{1}}".
 */
class TemplateRenderer
{
    /**
     * @param  array  $sentComponents  payload['template']['components'] (may be empty)
     * @return array{header: ?string, body: string, footer: ?string, buttons: list<array{type:string,text:?string}>}
     */
    public static function render(?MessageTemplate $template, array $sentComponents = []): array
    {
        $components = $template?->components ?? [];
        $params = self::parameters($sentComponents);

        $header = null;
        $body = '';
        $footer = null;
        $buttons = [];

        foreach ($components as $c) {
            switch (strtoupper($c['type'] ?? '')) {
                case 'HEADER':
                    $format = strtoupper($c['format'] ?? 'TEXT');
                    $header = $format === 'TEXT' ? self::fill($c['text'] ?? '', $params['header']) : '['.ucfirst(strtolower($format)).']';
                    break;
                case 'BODY':
                    $body = self::fill($c['text'] ?? '', $params['body']);
                    break;
                case 'FOOTER':
                    $footer = $c['text'] ?? null;
                    break;
                case 'BUTTONS':
                    foreach ($c['buttons'] ?? [] as $b) {
                        $buttons[] = ['type' => $b['type'] ?? 'QUICK_REPLY', 'text' => $b['text'] ?? null];
                    }
                    break;
            }
        }

        if ($template?->category === 'AUTHENTICATION' && $body === '') {
            $body = ($params['body'][0] ?? '{{1}}').' is your verification code.';
        }

        return ['header' => $header, 'body' => $body, 'footer' => $footer, 'buttons' => $buttons];
    }

    /** One-line text for previews / conversation lists. */
    public static function summary(?MessageTemplate $template, array $sentComponents = []): string
    {
        $r = self::render($template, $sentComponents);

        return trim(($r['header'] ? $r['header'].' — ' : '').$r['body']);
    }

    /** Extract positional text parameters per component from what was sent to Meta. */
    protected static function parameters(array $sentComponents): array
    {
        $out = ['header' => [], 'body' => []];

        foreach ($sentComponents as $c) {
            $type = strtolower($c['type'] ?? '');
            if (! in_array($type, ['header', 'body'], true)) {
                continue;
            }
            foreach ($c['parameters'] ?? [] as $p) {
                $out[$type][] = match ($p['type'] ?? '') {
                    'text' => (string) ($p['text'] ?? ''),
                    'currency' => (string) ($p['currency']['fallback_value'] ?? ''),
                    'date_time' => (string) ($p['date_time']['fallback_value'] ?? ''),
                    default => '',
                };
            }
        }

        return $out;
    }

    /** Replace {{1}}, {{2}} … (and named {{name}} params in order) with the given values. */
    public static function fill(string $text, array $values): string
    {
        $i = 0;

        return preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/', function ($m) use (&$i, $values) {
            $key = $m[1];
            $value = is_numeric($key) ? ($values[(int) $key - 1] ?? null) : ($values[$i] ?? null);
            $i++;

            return $value !== null && $value !== '' ? $value : $m[0];
        }, $text);
    }
}
