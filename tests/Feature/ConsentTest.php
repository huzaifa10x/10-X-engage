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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsentTest extends TestCase
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
        Http::fake(['graph.facebook.com/*/PN1/messages' => Http::response(['messaging_product' => 'whatsapp', 'contacts' => [['wa_id' => '923121057349']], 'messages' => [['id' => 'wamid.'.uniqid()]]])]);
    }

    protected function inbound(string $text, string $from = '923121057349'): void
    {
        $event = WebhookEvent::create(['object' => 'whatsapp_business_account', 'waba_id' => '111', 'field' => 'messages', 'payload' => ['change' => [
            'field' => 'messages',
            'value' => [
                'messaging_product' => 'whatsapp',
                'metadata' => ['display_phone_number' => '971585496310', 'phone_number_id' => 'PN1'],
                'contacts' => [['profile' => ['name' => 'Ali'], 'wa_id' => $from]],
                'messages' => [['from' => $from, 'id' => 'wamid.'.uniqid(), 'timestamp' => (string) now()->timestamp, 'text' => ['body' => $text], 'type' => 'text']],
            ],
        ]]]);
        (new ProcessWhatsAppWebhook($event->id))->handle();
    }

    public function test_stop_keyword_opts_out_suppresses_and_auto_replies(): void
    {
        $this->inbound('STOP.');

        $contact = Contact::where('wa_id', '923121057349')->firstOrFail();
        $this->assertSame('opted_out', $contact->opt_in_status);
        $this->assertTrue($contact->isSuppressed());
        $this->assertDatabaseHas('consent_events', ['wa_id' => '923121057349', 'action' => 'opt_out', 'source' => 'keyword']);
        $this->assertDatabaseHas('messages', ['direction' => 'outbound', 'origin' => 'system', 'contact_id' => $contact->id]);
        Http::assertSent(fn ($r) => str_contains($r['text']['body'] ?? '', 'unsubscribed'));

        // Template (business-initiated) is now blocked
        $template = MessageTemplate::create(['whatsapp_account_id' => $this->account->id, 'template_id' => 't', 'name' => 'promo', 'language' => 'en_US', 'category' => 'MARKETING', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hi']]]);
        $this->actingAs($this->user)->postJson(route('inbox.send', $contact), ['type' => 'template', 'template' => ['id' => $template->id]])
            ->assertStatus(422)->assertJsonPath('errors.type.0', fn ($m) => str_contains($m, 'opted out'));

        // but a free-form reply inside the window is still allowed (customer-initiated)
        $this->actingAs($this->user)->postJson(route('inbox.send', $contact), ['type' => 'text', 'text' => ['body' => 'Noted']])->assertOk();

        $this->inbound('start');
        $this->assertSame('opted_in', $contact->fresh()->opt_in_status);
        $this->assertFalse($contact->fresh()->isSuppressed());
    }

    public function test_substring_does_not_trigger_keywords(): void
    {
        $this->inbound("please don't stop my class");
        $this->assertSame('unknown', Contact::where('wa_id', '923121057349')->first()->opt_in_status);
    }

    public function test_manual_consent_update_and_ledger(): void
    {
        $contact = Contact::create(['workspace_id' => $this->user->workspace_id, 'wa_id' => '971501234567', 'name' => 'Sara']);

        $this->actingAs($this->user)->put(route('contacts.consent', $contact), ['status' => 'opted_in', 'scope' => ['marketing'], 'consent_text' => 'Web form'])->assertRedirect();
        $this->assertSame(['marketing'], $contact->fresh()->opt_in_scope);
        $this->assertDatabaseHas('consent_events', ['contact_id' => $contact->id, 'action' => 'opt_in', 'source' => 'ui', 'consent_text' => 'Web form', 'user_id' => $this->user->id]);
    }

    public function test_csv_import_creates_updates_and_opts_in(): void
    {
        Contact::create(['workspace_id' => $this->user->workspace_id, 'wa_id' => '971501234567', 'name' => 'Old Name', 'tags' => ['existing']]);

        $csv = "Name,WhatsApp,Email,Tags,Opt in\nSara New,+971 50 123 4567,sara@x.com,\"vip, gold\",yes\nAli,00923121057349,,lead,no\nBad,12,,,\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $preview = $this->actingAs($this->user)->post(route('contacts.import.preview'), ['file' => $file])->assertOk()->json();
        $this->assertSame(['0' => 'name', '1' => 'phone', '2' => 'email', '3' => 'tags', '4' => 'opt_in'], $preview['mapping']);

        $this->actingAs($this->user)->post(route('contacts.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv),
            'mapping' => $preview['mapping'],
            'default_tags' => 'sep-2026',
        ])->assertRedirect(route('contacts.index'));

        $sara = Contact::where('wa_id', '971501234567')->firstOrFail();
        $this->assertSame('Sara New', $sara->name);
        $this->assertEqualsCanonicalizing(['existing', 'vip', 'gold', 'sep-2026'], $sara->tags);
        $this->assertSame('opted_in', $sara->opt_in_status);

        $ali = Contact::where('wa_id', '923121057349')->firstOrFail();
        $this->assertSame('import', $ali->source);
        $this->assertSame('opted_out', $ali->opt_in_status);
        $this->assertDatabaseMissing('contacts', ['wa_id' => '12']);
    }
}
