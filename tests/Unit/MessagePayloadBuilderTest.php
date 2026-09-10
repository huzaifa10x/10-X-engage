<?php

namespace Tests\Unit;

use App\Support\MessagePayloadBuilder;
use PHPUnit\Framework\TestCase;

class MessagePayloadBuilderTest extends TestCase
{
    public function test_text_message_matches_collection_shape(): void
    {
        $payload = (new MessagePayloadBuilder('+15551234567'))->text('hello', false);

        $this->assertSame([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => '+15551234567',
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => 'hello'],
        ], $payload);
    }

    public function test_reply_adds_context(): void
    {
        $payload = (new MessagePayloadBuilder('15551234567', 'wamid.abc'))->text('hi');

        $this->assertSame(['message_id' => 'wamid.abc'], $payload['context']);
    }

    public function test_media_by_link_and_by_id(): void
    {
        $byLink = (new MessagePayloadBuilder('1'))->media('image', null, 'https://x/y.jpg', 'cap');
        $byId = (new MessagePayloadBuilder('1'))->media('document', '123', null, null, 'file.pdf');

        $this->assertSame(['link' => 'https://x/y.jpg', 'caption' => 'cap'], $byLink['image']);
        $this->assertSame(['id' => '123', 'filename' => 'file.pdf'], $byId['document']);
    }

    public function test_audio_ignores_caption(): void
    {
        $payload = (new MessagePayloadBuilder('1'))->media('audio', null, 'https://x/a.ogg', 'ignored');

        $this->assertSame(['link' => 'https://x/a.ogg'], $payload['audio']);
    }

    public function test_template_message(): void
    {
        $payload = (new MessagePayloadBuilder('1'))->template('hello_world', 'en_US');

        $this->assertSame('template', $payload['type']);
        $this->assertSame(['name' => 'hello_world', 'language' => ['code' => 'en_US']], $payload['template']);
    }

    public function test_reply_buttons(): void
    {
        $payload = (new MessagePayloadBuilder('1'))->replyButtons('Pick one', [['id' => 'a', 'title' => 'A'], ['id' => 'b', 'title' => 'B']]);

        $this->assertSame('interactive', $payload['type']);
        $this->assertSame('button', $payload['interactive']['type']);
        $this->assertCount(2, $payload['interactive']['action']['buttons']);
        $this->assertSame(['type' => 'reply', 'reply' => ['id' => 'a', 'title' => 'A']], $payload['interactive']['action']['buttons'][0]);
    }
}
