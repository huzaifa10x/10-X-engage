<?php

namespace Tests\Feature;

use App\Actions\Broadcasts\LaunchBroadcast;
use App\Actions\Messaging\SendMessage;
use App\Jobs\ProcessBroadcast;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Jobs\SendMessageJob;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PhoneNumber;
use App\Models\Segment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected PhoneNumber $phone;

    protected MessageTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $workspace = Workspace::create(['name' => 'W', 'slug' => 'w']);
        $this->user = User::factory()->create(['workspace_id' => $workspace->id]);
        $account = WhatsAppAccount::create(['workspace_id' => $workspace->id, 'waba_id' => '111', 'access_token' => 'T', 'status' => 'active']);
        $this->phone = PhoneNumber::create(['whatsapp_account_id' => $account->id, 'phone_number_id' => 'PN1', 'display_phone_number' => '+971 58 549 6310', 'is_registered' => true, 'is_default' => true]);
        $this->template = MessageTemplate::create(['whatsapp_account_id' => $account->id, 'template_id' => 't', 'name' => 'promo', 'language' => 'en_US', 'category' => 'MARKETING', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, offer for {{2}}']]]);

        foreach ([['971500000001', 'Ali Khan', ['vip'], 'opted_in'], ['971500000002', 'Sara', ['vip'], 'unknown'], ['971500000003', 'Omar', ['vip'], 'opted_out'], ['971500000004', 'Lina', ['lead'], 'opted_in']] as [$wa, $name, $tags, $opt]) {
            Contact::create(['workspace_id' => $workspace->id, 'wa_id' => $wa, 'phone' => '+'.$wa, 'name' => $name, 'tags' => $tags, 'opt_in_status' => $opt, 'company' => 'Acme']);
        }

        Http::fake(['graph.facebook.com/*/PN1/messages' => function ($request) {
            static $n = 0;
            $n++;

            return Http::response(['messaging_product' => 'whatsapp', 'contacts' => [['wa_id' => $request['to']]], 'messages' => [['id' => "wamid.b{$n}"]]]);
        }]);
    }

    public function test_segment_rules_and_count_endpoint(): void
    {
        $rules = [['field' => 'tags', 'op' => 'contains', 'value' => 'vip'], ['field' => 'company', 'op' => 'eq', 'value' => 'Acme']];

        $this->actingAs($this->user)->postJson(route('segments.count'), ['rules' => $rules, 'match' => 'all'])
            ->assertOk()->assertJson(['match' => 3, 'eligible' => 2]);           // Omar opted out

        $this->actingAs($this->user)->postJson(route('segments.count'), ['rules' => $rules, 'match' => 'all', 'require_opt_in' => true])
            ->assertJson(['match' => 3, 'eligible' => 1]);                        // only Ali

        $this->actingAs($this->user)->post(route('segments.store'), ['name' => 'VIPs', 'match' => 'all', 'rules' => $rules])->assertRedirect(route('segments.index'));
        $this->assertSame(3, Segment::first()->contacts()->count());
    }

    public function test_broadcast_sends_personalised_templates_and_tracks_status(): void
    {
        Queue::fake();
        $segment = Segment::create(['workspace_id' => $this->user->workspace_id, 'name' => 'VIPs', 'match' => 'all', 'rules' => [['field' => 'tags', 'op' => 'contains', 'value' => 'vip']]]);

        $this->actingAs($this->user)->post(route('broadcasts.store'), [
            'name' => 'Promo', 'phone_number_id' => $this->phone->id, 'message_template_id' => $this->template->id, 'segment_id' => $segment->id,
            'template_params' => ['header' => [], 'body' => ['{{contact.first_name}}', '{{contact.company}}'], 'buttons' => []],
            'action' => 'send',
        ])->assertRedirect();

        $broadcast = Broadcast::firstOrFail();
        $this->assertSame('queued', $broadcast->status);
        $this->assertSame(3, $broadcast->total_count);
        $this->assertSame(1, $broadcast->skipped_count);     // Omar (opted out)
        Queue::assertPushed(ProcessBroadcast::class);

        // Run the pipeline synchronously
        (new ProcessBroadcast($broadcast->id))->handle(app(SendMessage::class));
        $broadcast->refresh();
        $this->assertSame('sending', $broadcast->status);
        $this->assertSame(2, Message::where('broadcast_id', $broadcast->id)->where('status', 'queued')->count());
        Queue::assertPushed(SendMessageJob::class, 2);

        foreach (Message::where('broadcast_id', $broadcast->id)->get() as $m) {
            (new SendMessageJob($m->id))->handle(app(SendMessage::class));
        }

        $ali = Message::where('to', '971500000001')->firstOrFail();
        $this->assertSame('accepted', $ali->status);
        $this->assertSame('Hi Ali, offer for Acme', $ali->preview);
        $this->assertSame('Ali', $ali->payload['template']['components'][0]['parameters'][0]['text']);

        $broadcast->refresh();
        $this->assertSame(2, $broadcast->sent_count);
        $this->assertSame('completed', $broadcast->status);

        // Delivered + read webhooks move the counters
        foreach ([['delivered', $ali->wamid], ['read', $ali->wamid]] as [$status, $wamid]) {
            $event = WebhookEvent::create(['object' => 'whatsapp_business_account', 'waba_id' => '111', 'field' => 'messages', 'payload' => ['change' => [
                'field' => 'messages',
                'value' => ['messaging_product' => 'whatsapp', 'metadata' => ['phone_number_id' => 'PN1', 'display_phone_number' => 'x'], 'statuses' => [['id' => $wamid, 'status' => $status, 'timestamp' => (string) now()->timestamp, 'recipient_id' => '971500000001']]],
            ]]]);
            (new ProcessWhatsAppWebhook($event->id))->handle();
        }
        $broadcast->refresh();
        $this->assertSame(1, $broadcast->delivered_count);
        $this->assertSame(1, $broadcast->read_count);

        $this->actingAs($this->user)->get(route('broadcasts.show', $broadcast))->assertOk();
        $this->actingAs($this->user)->getJson(route('broadcasts.progress', $broadcast))->assertJsonPath('counts.read', 1);
    }

    public function test_scheduled_broadcast_is_launched_by_command(): void
    {
        Queue::fake();
        $this->actingAs($this->user)->post(route('broadcasts.store'), [
            'name' => 'Later', 'phone_number_id' => $this->phone->id, 'message_template_id' => $this->template->id,
            'template_params' => ['body' => ['x', 'y']], 'scheduled_at' => now()->subMinute()->toDateTimeString(), 'action' => 'schedule',
        ])->assertRedirect();

        $this->assertSame('scheduled', Broadcast::first()->status);
        $this->artisan('engage:dispatch-broadcasts')->assertSuccessful();
        $this->assertSame('queued', Broadcast::first()->status);
        $this->assertSame(4, Broadcast::first()->total_count);   // all contacts
        app(LaunchBroadcast::class); // resolvable
    }
}
