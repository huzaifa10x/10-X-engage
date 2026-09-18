<?php

namespace Tests\Feature;

use App\Actions\Templates\SyncTemplates;
use App\Models\MessageTemplate;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TemplateSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_removes_templates_deleted_on_meta(): void
    {
        $workspace = Workspace::create(['name' => 'W', 'slug' => 'w']);
        $account = WhatsAppAccount::create(['workspace_id' => $workspace->id, 'waba_id' => '111', 'access_token' => 'T']);

        MessageTemplate::create(['whatsapp_account_id' => $account->id, 'template_id' => '1', 'name' => 'keep_me', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => []]);
        MessageTemplate::create(['whatsapp_account_id' => $account->id, 'template_id' => '2', 'name' => 'gone', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => []]);
        MessageTemplate::create(['whatsapp_account_id' => $account->id, 'template_id' => '3', 'name' => 'soft_deleted', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => []]);

        Http::fake([
            'graph.facebook.com/*/111/message_templates*' => Http::response(['data' => [
                ['id' => '1', 'name' => 'keep_me', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'hi']]],
                ['id' => '3', 'name' => 'soft_deleted', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'DELETED', 'components' => []],
                ['id' => '4', 'name' => 'brand_new', 'language' => 'en_US', 'category' => 'MARKETING', 'status' => 'PENDING', 'components' => []],
            ]]),
            'graph.facebook.com/*/111*' => Http::response(['message_template_namespace' => 'ns']),
        ]);

        app(SyncTemplates::class)($account);

        $this->assertSame(['brand_new', 'keep_me'], MessageTemplate::orderBy('name')->pluck('name')->all());
    }
}
