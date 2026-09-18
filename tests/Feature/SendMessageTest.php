<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_message_sends_even_when_other_sections_are_submitted_empty(): void
    {
        Http::fake(['graph.facebook.com/*/PN1/messages' => Http::response([
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => '+923121057349', 'wa_id' => '923121057349']],
            'messages' => [['id' => 'wamid.test']],
        ])]);

        $workspace = Workspace::create(['name' => 'W', 'slug' => 'w']);
        $user = User::factory()->create(['workspace_id' => $workspace->id]);
        $account = WhatsAppAccount::create(['workspace_id' => $workspace->id, 'waba_id' => '1', 'access_token' => 'T', 'status' => 'active']);
        $phone = PhoneNumber::create(['whatsapp_account_id' => $account->id, 'phone_number_id' => 'PN1', 'display_phone_number' => '+971 58 549 6310', 'is_registered' => true]);
        Contact::create(['workspace_id' => $workspace->id, 'wa_id' => '923121057349', 'name' => 'Test', 'window_expires_at' => now()->addHours(2), 'window_opened_by' => 'inbound', 'last_message_at' => now()]);

        // Exactly what the composer submits for a text message
        $response = $this->actingAs($user)->post(route('messages.store'), [
            'phone_number_id' => $phone->id,
            'to' => '923121057349',
            'type' => 'text',
            'reply_to' => '',
            'text' => ['body' => 'Hi', 'preview_url' => false],
            'media' => ['source' => 'link', 'link' => '', 'id' => '', 'caption' => '', 'filename' => ''],
            'location' => ['latitude' => '', 'longitude' => '', 'name' => '', 'address' => ''],
            'template' => ['id' => '', 'header' => [], 'body' => [], 'buttons' => []],
            'interactive' => ['kind' => 'button', 'header' => '', 'body' => '', 'footer' => '', 'buttons' => [['id' => 'btn_1', 'title' => '']]],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('messages', ['wamid' => 'wamid.test', 'status' => 'accepted', 'to' => '923121057349']);
        Http::assertSent(fn ($r) => $r['to'] === '923121057349' && $r['text']['body'] === 'Hi');
    }
}
