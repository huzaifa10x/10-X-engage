<?php

namespace Tests\Feature;

use App\Jobs\DownloadInboundMedia;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\MediaAsset;
use App\Models\Message;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use App\Services\Meta\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboundMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_image_is_downloaded_stored_and_served(): void
    {
        Storage::fake('local');
        Queue::fake();
        Http::fake([
            'graph.facebook.com/*/MEDIA123*' => Http::response(['url' => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=MEDIA123', 'mime_type' => 'image/jpeg', 'sha256' => 'abc', 'file_size' => 3, 'id' => 'MEDIA123']),
            'lookaside.fbsbx.com/*' => Http::response('JPG', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $workspace = Workspace::create(['name' => 'W', 'slug' => 'w']);
        $user = User::factory()->create(['workspace_id' => $workspace->id]);
        $account = WhatsAppAccount::create(['workspace_id' => $workspace->id, 'waba_id' => '111', 'access_token' => 'T']);
        PhoneNumber::create(['whatsapp_account_id' => $account->id, 'phone_number_id' => 'PN1', 'display_phone_number' => '+1', 'is_registered' => true]);

        $event = WebhookEvent::create(['object' => 'whatsapp_business_account', 'waba_id' => '111', 'field' => 'messages', 'payload' => ['change' => [
            'field' => 'messages',
            'value' => [
                'messaging_product' => 'whatsapp',
                'metadata' => ['display_phone_number' => '1', 'phone_number_id' => 'PN1'],
                'contacts' => [['profile' => ['name' => 'Ali'], 'wa_id' => '923121057349']],
                'messages' => [['from' => '923121057349', 'id' => 'wamid.img', 'timestamp' => (string) now()->timestamp, 'type' => 'image', 'image' => ['id' => 'MEDIA123', 'mime_type' => 'image/jpeg', 'sha256' => 'abc', 'caption' => 'Receipt']]],
            ],
        ]]]);
        (new ProcessWhatsAppWebhook($event->id))->handle();

        $message = Message::where('wamid', 'wamid.img')->firstOrFail();
        $asset = MediaAsset::findOrFail($message->media_asset_id);
        $this->assertSame('inbound', $asset->direction);
        Queue::assertPushed(DownloadInboundMedia::class);

        (new DownloadInboundMedia($asset->id))->handle(app(MessagingService::class));

        $asset->refresh();
        $this->assertTrue($asset->isStored());
        $this->assertStringEndsWith('.jpg', $asset->storage_path);
        Storage::disk('local')->assertExists($asset->storage_path);

        $this->actingAs($user)->getJson(route('inbox.messages', $message->contact_id))
            ->assertJsonPath('messages.0.body.media.url', route('media.show', $asset))
            ->assertJsonPath('messages.0.body.media.caption', 'Receipt');

        $this->actingAs($user)->get(route('media.show', $asset))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $other = User::factory()->create(['workspace_id' => Workspace::create(['name' => 'X', 'slug' => 'x'])->id]);
        $this->actingAs($other)->get(route('media.show', $asset))->assertForbidden();
    }
}
