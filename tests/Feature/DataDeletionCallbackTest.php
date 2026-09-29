<?php

namespace Tests\Feature;

use Tests\TestCase;

class DataDeletionCallbackTest extends TestCase
{
    public function test_valid_signed_request_returns_confirmation(): void
    {
        config(['whatsapp.app.secret' => 'secret']);
        $payload = rtrim(strtr(base64_encode(json_encode(['user_id' => 'TEST_123', 'algorithm' => 'HMAC-SHA256', 'issued_at' => time()])), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, 'secret', true)), '+/', '-_'), '=');

        $this->post('/api/data-deletion-callback', ['signed_request' => "{$sig}.{$payload}"])
            ->assertOk()->assertJsonStructure(['url', 'confirmation_code']);

        $this->post('/api/data-deletion-callback', ['signed_request' => "bad.{$payload}"])->assertStatus(400);
    }
}
