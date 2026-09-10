<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Contact;
use App\Models\Message;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.webhook.verify_token' => 'verify-me', 'whatsapp.app.secret' => 'secret']);
    }

    public function test_verification_handshake_returns_challenge(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_verification_rejects_bad_token(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1')->assertForbidden();
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->postJson('/webhooks/whatsapp', ['object' => 'whatsapp_business_account'], ['X-Hub-Signature-256' => 'sha256=bad'])
            ->assertStatus(401);
    }

    public function test_valid_payload_is_stored_and_queued(): void
    {
        Queue::fake();

        $body = json_encode($this->statusPayload('wamid.1', 'delivered'));
        $sig = 'sha256='.hash_hmac('sha256', $body, 'secret');

        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sig, 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk();

        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessWhatsAppWebhook::class);
    }

    public function test_status_update_marks_message_delivered_and_inbound_message_is_stored(): void
    {
        [$workspace, $phone] = $this->fixtures();

        $message = Message::create([
            'workspace_id' => $workspace->id, 'phone_number_id' => $phone->id, 'direction' => 'outbound',
            'type' => 'text', 'status' => 'accepted', 'to' => '16315551234', 'wamid' => 'wamid.1',
        ]);

        $event = WebhookEvent::create(['object' => 'whatsapp_business_account', 'waba_id' => '111', 'field' => 'messages', 'payload' => [
            'change' => $this->statusPayload('wamid.1', 'delivered')['entry'][0]['changes'][0],
        ]]);
        (new ProcessWhatsAppWebhook($event->id))->handle();

        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->delivered_at);

        $inbound = WebhookEvent::create(['object' => 'whatsapp_business_account', 'waba_id' => '111', 'field' => 'messages', 'payload' => ['change' => [
            'field' => 'messages',
            'value' => [
                'messaging_product' => 'whatsapp',
                'metadata' => ['display_phone_number' => '16505553333', 'phone_number_id' => 'PN1'],
                'contacts' => [['profile' => ['name' => 'Kerry Fisher'], 'wa_id' => '16315551234']],
                'messages' => [['from' => '16315551234', 'id' => 'wamid.in', 'timestamp' => '1603059201', 'text' => ['body' => 'Hello'], 'type' => 'text']],
            ],
        ]]]);
        (new ProcessWhatsAppWebhook($inbound->id))->handle();

        $this->assertDatabaseHas('messages', ['wamid' => 'wamid.in', 'direction' => 'inbound', 'preview' => 'Hello']);
        $this->assertSame('Kerry Fisher', Contact::where('wa_id', '16315551234')->first()->name);
    }

    protected function fixtures(): array
    {
        $workspace = Workspace::create(['name' => 'W', 'slug' => 'w']);
        User::factory()->create(['workspace_id' => $workspace->id]);
        $account = WhatsAppAccount::create(['workspace_id' => $workspace->id, 'waba_id' => '111', 'access_token' => 'tok']);
        $phone = PhoneNumber::create(['whatsapp_account_id' => $account->id, 'phone_number_id' => 'PN1', 'display_phone_number' => '+1 650-555-3333', 'is_registered' => true]);

        return [$workspace, $phone];
    }

    protected function statusPayload(string $wamid, string $status): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [[
            'id' => '111',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '16505553333', 'phone_number_id' => 'PN1'],
                    'statuses' => [['id' => $wamid, 'status' => $status, 'timestamp' => '1603086313', 'recipient_id' => '16315551234']],
                ],
            ]],
        ]]];
    }
}
