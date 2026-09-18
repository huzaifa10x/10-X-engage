<?php

namespace App\Support;

use App\Models\MessageTemplate;
use App\Models\PhoneNumber;
use InvalidArgumentException;

/**
 * Turns validated composer / inbox input into the exact Cloud API Messages Object.
 * Returns [payload, human preview, ?MessageTemplate].
 */
class OutboundMessageFactory
{
    /**
     * @param  array  $data  validated SendMessageRequest / InboxSendRequest data
     * @return array{0: array, 1: string, 2: ?MessageTemplate}
     *
     * @throws InvalidArgumentException
     */
    public static function build(array $data, PhoneNumber $phone, string $to, ?string $replyTo = null): array
    {
        $builder = new MessagePayloadBuilder(preg_replace('/[^\d+]/', '', $to), $replyTo ?: null);

        return match ($data['type']) {
            'text' => [
                $builder->text($data['text']['body'], (bool) ($data['text']['preview_url'] ?? false)),
                $data['text']['body'],
                null,
            ],
            'image', 'video', 'document', 'audio', 'sticker' => [
                $builder->media(
                    $data['type'],
                    ($data['media']['source'] ?? 'link') === 'id' ? ($data['media']['id'] ?? null) : null,
                    ($data['media']['source'] ?? 'link') === 'link' ? ($data['media']['link'] ?? null) : null,
                    $data['media']['caption'] ?? null,
                    $data['media']['filename'] ?? null,
                ),
                ucfirst($data['type']).(($data['media']['caption'] ?? '') !== '' ? ': '.$data['media']['caption'] : ''),
                null,
            ],
            'location' => [
                $builder->location($data['location']['latitude'], $data['location']['longitude'], $data['location']['name'] ?? null, $data['location']['address'] ?? null),
                'Location: '.($data['location']['name'] ?? "{$data['location']['latitude']},{$data['location']['longitude']}"),
                null,
            ],
            'interactive' => self::interactive($builder, $data['interactive'] ?? []),
            'template' => self::template($builder, $data['template'] ?? [], $phone),
            default => throw new InvalidArgumentException("Unsupported message type {$data['type']}."),
        };
    }

    protected static function interactive(MessagePayloadBuilder $builder, array $i): array
    {
        if (($i['kind'] ?? 'button') === 'list') {
            $sections = array_values(array_filter($i['sections'] ?? [], fn ($s) => ! empty($s['rows'])));

            return [
                $builder->list($i['body'], $i['button_text'] ?? 'Choose', $sections, $i['header'] ?? null, $i['footer'] ?? null),
                'List: '.$i['body'],
                null,
            ];
        }

        return [
            $builder->replyButtons($i['body'], $i['buttons'] ?? [], $i['header'] ?? null, $i['footer'] ?? null),
            'Buttons: '.$i['body'],
            null,
        ];
    }

    protected static function template(MessagePayloadBuilder $builder, array $input, PhoneNumber $phone): array
    {
        $template = MessageTemplate::findOrFail($input['id'] ?? 0);

        if ($template->whatsapp_account_id !== $phone->whatsapp_account_id) {
            throw new InvalidArgumentException('This template belongs to a different WhatsApp Business Account.');
        }
        if (! $template->isSendable()) {
            throw new InvalidArgumentException("Template {$template->name} is {$template->status}; only APPROVED templates can be sent.");
        }

        $components = MessagePayloadBuilder::templateComponents($template, $input);

        return [
            $builder->template($template->name, $template->language, $components),
            'Template '.$template->name.': '.TemplatePayloadBuilder::summarize($template->components),
            $template,
        ];
    }
}
