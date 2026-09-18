<?php

namespace Tests\Unit;

use App\Models\MessageTemplate;
use App\Support\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    public function test_fills_body_and_header_variables_from_sent_parameters(): void
    {
        $template = new MessageTemplate(['category' => 'UTILITY', 'components' => [
            ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Order {{1}}'],
            ['type' => 'BODY', 'text' => 'Hello {{1}}, your order {{2}} has shipped.'],
            ['type' => 'FOOTER', 'text' => 'Thanks'],
            ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'Track', 'url' => 'https://x/{{1}}']]],
        ]]);

        $sent = [
            ['type' => 'header', 'parameters' => [['type' => 'text', 'text' => '#42']]],
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Daniyal'], ['type' => 'text', 'text' => '#42']]],
        ];

        $r = TemplateRenderer::render($template, $sent);

        $this->assertSame('Order #42', $r['header']);
        $this->assertSame('Hello Daniyal, your order #42 has shipped.', $r['body']);
        $this->assertSame('Thanks', $r['footer']);
        $this->assertSame('Track', $r['buttons'][0]['text']);
        $this->assertSame('Order #42 — Hello Daniyal, your order #42 has shipped.', TemplateRenderer::summary($template, $sent));
    }

    public function test_leaves_placeholders_when_no_parameters_were_sent(): void
    {
        $template = new MessageTemplate(['category' => 'MARKETING', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']]]);

        $this->assertSame('Hi {{1}}', TemplateRenderer::render($template, [])['body']);
    }
}
