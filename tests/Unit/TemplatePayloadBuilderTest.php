<?php

namespace Tests\Unit;

use App\Support\TemplatePayloadBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TemplatePayloadBuilderTest extends TestCase
{
    public function test_builds_marketing_template_with_all_components(): void
    {
        $payload = TemplatePayloadBuilder::build([
            'name' => 'seasonal_promotion_text_only',
            'language' => 'en',
            'category' => 'MARKETING',
            'header' => ['format' => 'TEXT', 'text' => 'Our {{1}} is on!', 'example' => 'Summer Sale'],
            'body' => ['text' => 'Shop now through {{1}} and use code {{2}} to get {{3}} off.', 'examples' => ['the end of August', '25OFF', '25%']],
            'footer' => ['text' => 'Use the buttons below to manage your marketing subscriptions'],
            'buttons' => [
                ['type' => 'QUICK_REPLY', 'text' => 'Unsubscribe from Promos'],
                ['type' => 'URL', 'text' => 'Shop Now', 'url' => 'https://www.examplesite.com/shop?promo={{1}}', 'example' => 'summer2023'],
                ['type' => 'PHONE_NUMBER', 'text' => 'Call', 'phone_number' => '+1 555 005 1310'],
            ],
        ]);

        $this->assertSame('seasonal_promotion_text_only', $payload['name']);
        $this->assertSame('MARKETING', $payload['category']);
        $this->assertSame(['HEADER', 'BODY', 'FOOTER', 'BUTTONS'], array_column($payload['components'], 'type'));
        $this->assertSame(['header_text' => ['Summer Sale']], $payload['components'][0]['example']);
        $this->assertSame([['the end of August', '25OFF', '25%']], $payload['components'][1]['example']['body_text']);
        $this->assertSame(['summer2023'], $payload['components'][3]['buttons'][1]['example']);
        $this->assertSame('+15550051310', $payload['components'][3]['buttons'][2]['phone_number']);
    }

    public function test_builds_authentication_template_with_otp_button(): void
    {
        $payload = TemplatePayloadBuilder::build([
            'name' => 'authentication_code_copy_code_button',
            'language' => 'en_US',
            'category' => 'AUTHENTICATION',
            'body' => ['add_security_recommendation' => true],
            'footer' => ['code_expiration_minutes' => 10],
            'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => 'Copy Code']],
        ]);

        $this->assertSame([
            ['type' => 'BODY', 'add_security_recommendation' => true],
            ['type' => 'FOOTER', 'code_expiration_minutes' => 10],
            ['type' => 'BUTTONS', 'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => 'Copy Code']]],
        ], $payload['components']);
    }

    public function test_media_header_requires_upload_handle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TemplatePayloadBuilder::build([
            'name' => 'x', 'language' => 'en', 'category' => 'UTILITY',
            'header' => ['format' => 'IMAGE'],
            'body' => ['text' => 'Hello'],
        ]);
    }

    public function test_body_variables_require_examples(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TemplatePayloadBuilder::build([
            'name' => 'x', 'language' => 'en', 'category' => 'UTILITY',
            'body' => ['text' => 'Hi {{1}}', 'examples' => []],
        ]);
    }
}
