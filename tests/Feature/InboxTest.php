<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected PhoneNumber $phone;

    protected WhatsAppAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::create(['name' => 'W', 'slug' => 'w']);
        $this->user = User::factory()->create(['workspace_id' => $workspace->id]);
        $this->account = WhatsAppAccount::create(['workspace_id' => $workspace->id, 'waba_id' => '111', 'access_token' => 'T', 'status' => 'active']);
        $this->phone = PhoneNumber::create(['whatsapp_account_id' => $this->account->id, 'phone_number_id' => 'PN1', 'display_phone_number' => '+971 58 549 6310', 'is_registered' => true, 'is_default' => true]);

        Http::fake(['graph.facebook.com/*/PN1/messages' => Http::response([
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => '+923121057349', 'wa_id' => '923121057349']],
            'messages' => [['id' => 'wamid.'.uniqid()]],
        ])]);
    }

    protected function contact(array $attrs = []): Contact
    {
        return Contact::create(['workspace_id' => $this->user->workspace_id, 'wa_id' => '923121057349', 'phone' => '+923121057349', 'name' => 'Ali'] + $attrs);
    }

    protected function template(): MessageTemplate
    {
        return MessageTemplate::create([
            'whatsapp_account_id' => $this->account->id, 'template_id' => 't1', 'name' => 'hello', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED',
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']],
        ]);
    }

    public function test_composer_rejects_numbers_that_are_not_contacts(): void
    {
        $this->actingAs($this->user)->post(route('messages.store'), [
            'phone_number_id' => $this->phone->id, 'to' => '+923121057349', 'type' => 'text', 'text' => ['body' => 'Hi'],
        ])->assertSessionHasErrors('to');

        Http::assertNothingSent();
    }

    public function test_free_form_message_is_blocked_when_no_window_is_open(): void
    {
        $contact = $this->contact();

        $this->actingAs($this->user)->postJson(route('inbox.send', $contact), ['type' => 'text', 'text' => ['body' => 'Hi']])
            ->assertStatus(422)
            ->assertJsonPath('errors.type.0', fn ($m) => str_contains($m, 'template'));

        Http::assertNothingSent();
    }

    public function test_free_form_message_is_blocked_after_window_expires(): void
    {
        $contact = $this->contact(['window_expires_at' => now()->subMinute(), 'window_opened_by' => 'inbound', 'last_message_at' => now()->subDay()]);

        $this->actingAs($this->user)->postJson(route('inbox.send', $contact), ['type' => 'text', 'text' => ['body' => 'Hi']])
            ->assertStatus(422)
            ->assertJsonPath('errors.type.0', '24-hour window has been closed. Send an approved template to open the conversation.');
    }

    public function test_template_is_allowed_and_opens_the_window(): void
    {
        $contact = $this->contact();
        $template = $this->template();

        $this->actingAs($this->user)->postJson(route('inbox.send', $contact), ['type' => 'template', 'template' => ['id' => $template->id, 'body' => ['Ali']]])
            ->assertOk()
            ->assertJsonPath('message.status', 'accepted')
            ->assertJsonPath('message.body.template.body', 'Hello Ali')
            ->assertJsonPath('message.preview', 'Hello Ali')
            ->assertJsonPath('contact.window.open', true)
            ->assertJsonPath('contact.window.opened_by', 'template');

        $this->assertTrue($contact->fresh()->isWindowOpen());

        // Free-form now allowed
        $this->actingAs($this->user)->postJson(route('inbox.send', $contact), ['type' => 'text', 'text' => ['body' => 'Thanks!']])
            ->assertOk()
            ->assertJsonPath('message.body.text', 'Thanks!');
    }

    public function test_inbound_message_creates_contact_opens_window_and_counts_unread(): void
    {
        $event = WebhookEvent::create(['object' => 'whatsapp_business_account', 'waba_id' => '111', 'field' => 'messages', 'payload' => ['change' => [
            'field' => 'messages',
            'value' => [
                'messaging_product' => 'whatsapp',
                'metadata' => ['display_phone_number' => '971585496310', 'phone_number_id' => 'PN1'],
                'contacts' => [['profile' => ['name' => 'Ali Khan'], 'wa_id' => '923121057349']],
                'messages' => [['from' => '923121057349', 'id' => 'wamid.in1', 'timestamp' => (string) now()->timestamp, 'text' => ['body' => 'Hello there'], 'type' => 'text']],
            ],
        ]]]);
        (new ProcessWhatsAppWebhook($event->id))->handle();

        $contact = Contact::where('wa_id', '923121057349')->firstOrFail();
        $this->assertSame('Ali Khan', $contact->name);
        $this->assertSame('inbound', $contact->source);
        $this->assertSame(1, $contact->unread_count);
        $this->assertTrue($contact->isWindowOpen());
        $this->assertSame('inbound', $contact->window_opened_by);
        $this->assertSame('Hello there', $contact->last_message_preview);

        // Admin can now reply free-form from the inbox
        $this->actingAs($this->user)->postJson(route('inbox.send', $contact), ['type' => 'text', 'text' => ['body' => 'Hi Ali']])->assertOk();

        // Polling endpoint returns both messages
        $this->actingAs($this->user)->getJson(route('inbox.messages', $contact))
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.direction', 'inbound')
            ->assertJsonPath('messages.1.direction', 'outbound');
    }

    public function test_unsupported_inbound_message_still_opens_the_window(): void
    {
        $contact = $this->contact();

        $event = WebhookEvent::create(['object' => 'whatsapp_business_account', 'waba_id' => '111', 'field' => 'messages', 'payload' => ['change' => [
            'field' => 'messages',
            'value' => [
                'messaging_product' => 'whatsapp',
                'metadata' => ['display_phone_number' => '971585496310', 'phone_number_id' => 'PN1'],
                'contacts' => [['profile' => ['name' => 'Ali'], 'wa_id' => '923121057349']],
                'messages' => [[
                    'from' => '923121057349', 'id' => 'wamid.unsup', 'timestamp' => (string) now()->timestamp, 'type' => 'unsupported',
                    'errors' => [['code' => 131060, 'title' => 'This message is currently unavailable.', 'message' => 'This message is currently unavailable.', 'error_data' => ['details' => 'Message is unavailable']]],
                ]],
            ],
        ]]]);
        (new ProcessWhatsAppWebhook($event->id))->handle();

        $contact->refresh();
        $this->assertTrue($contact->isWindowOpen());
        $this->assertSame('Unsupported message', $contact->last_message_preview);
        $this->assertSame(1, $contact->unread_count);

        $this->actingAs($this->user)->getJson(route('inbox.messages', $contact))
            ->assertJsonPath('messages.0.body.unsupported.code', 131060);

        $this->actingAs($this->user)->postJson(route('inbox.send', $contact), ['type' => 'text', 'text' => ['body' => 'Hi']])->assertOk();
    }

    public function test_window_self_heals_from_recent_inbound_message(): void
    {
        $contact = $this->contact();
        // inbound message stored by an outdated worker: no window, no summary
        $contact->messages()->create(['workspace_id' => $contact->workspace_id, 'phone_number_id' => $this->phone->id, 'direction' => 'inbound', 'type' => 'text', 'status' => 'received', 'from' => $contact->wa_id, 'preview' => 'hello hello', 'wamid' => 'wamid.stale', 'received_at' => now()->subMinutes(10)]);

        $this->actingAs($this->user)->get(route('inbox.show', $contact))->assertOk();
        $this->assertTrue($contact->fresh()->isWindowOpen());
        $this->assertSame('hello hello', $contact->fresh()->last_message_preview);

        $this->actingAs($this->user)->postJson(route('inbox.send', $contact), ['type' => 'text', 'text' => ['body' => 'Hi']])->assertOk();
    }

    public function test_rebuild_command_restores_window_from_messages(): void
    {
        $contact = $this->contact();
        $contact->messages()->create(['workspace_id' => $contact->workspace_id, 'phone_number_id' => $this->phone->id, 'direction' => 'inbound', 'type' => 'text', 'status' => 'received', 'from' => $contact->wa_id, 'preview' => 'Hello', 'wamid' => 'wamid.old', 'received_at' => now()->subHours(2)]);

        $this->artisan('engage:rebuild-conversations')->assertSuccessful();

        $contact->refresh();
        $this->assertTrue($contact->isWindowOpen());
        $this->assertSame('inbound', $contact->window_opened_by);
        $this->assertSame('Hello', $contact->last_message_preview);
    }

    public function test_contacts_crud(): void
    {
        $this->actingAs($this->user)->post(route('contacts.store'), ['name' => 'Sara', 'phone' => '+971 50 123 4567', 'tags' => ['lead', 'lead', ' vip '], 'email' => 'sara@example.com'])
            ->assertRedirect();

        $contact = Contact::where('wa_id', '971501234567')->firstOrFail();
        $this->assertSame('+971501234567', $contact->phone);
        $this->assertSame(['lead', 'vip'], $contact->tags);
        $this->assertSame('manual', $contact->source);

        // duplicate number rejected
        $this->actingAs($this->user)->post(route('contacts.store'), ['name' => 'Dup', 'phone' => '971501234567'])->assertSessionHasErrors('wa_id');

        $this->actingAs($this->user)->put(route('contacts.update', $contact), ['name' => 'Sara K', 'phone' => '+971501234567'])->assertRedirect(route('contacts.index'));
        $this->assertSame('Sara K', $contact->fresh()->name);

        $this->actingAs($this->user)->get(route('contacts.index'))->assertOk();
        $this->actingAs($this->user)->get(route('inbox.show', $contact))->assertOk();

        $this->actingAs($this->user)->delete(route('contacts.destroy', $contact))->assertRedirect(route('contacts.index'));
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_other_workspaces_cannot_access_contact(): void
    {
        $contact = $this->contact();
        $other = User::factory()->create(['workspace_id' => Workspace::create(['name' => 'X', 'slug' => 'x'])->id]);

        $this->actingAs($other)->get(route('inbox.show', $contact))->assertForbidden();
        $this->actingAs($other)->postJson(route('inbox.send', $contact), ['type' => 'text', 'text' => ['body' => 'x']])->assertForbidden();
    }
}
