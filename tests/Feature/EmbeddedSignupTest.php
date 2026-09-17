<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddedSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_runs_the_documented_onboarding_sequence(): void
    {
        config([
            'whatsapp.app.id' => 'APP', 'whatsapp.app.secret' => 'SECRET', 'whatsapp.embedded_signup.config_id' => 'CFG',
            'whatsapp.graph.version' => 'v25.0', 'whatsapp.partner.type' => 'tech_provider',
        ]);

        Http::fake([
            'graph.facebook.com/v25.0/oauth/access_token*' => Http::response(['access_token' => 'BUSINESS_TOKEN', 'token_type' => 'bearer']),
            'graph.facebook.com/v25.0/debug_token*' => Http::response(['data' => [
                'app_id' => 'APP', 'type' => 'BUSINESS', 'is_valid' => true, 'expires_at' => 0,
                'scopes' => ['whatsapp_business_management', 'whatsapp_business_messaging'],
                'granular_scopes' => [['scope' => 'whatsapp_business_management', 'target_ids' => ['524126980791429']]],
            ]]),
            'graph.facebook.com/v25.0/524126980791429/subscribed_apps' => Http::response(['success' => true]),
            'graph.facebook.com/v25.0/524126980791429/phone_numbers*' => Http::response(['data' => [[
                'id' => '106540352242922', 'display_phone_number' => '+1 631-555-1111', 'verified_name' => "John's Cake Shop",
                'quality_rating' => 'GREEN', 'code_verification_status' => 'VERIFIED', 'platform_type' => 'NOT_APPLICABLE',
            ]]]),
            'graph.facebook.com/v25.0/106540352242922/register' => Http::response(['success' => true]),
            'graph.facebook.com/v25.0/524126980791429*' => Http::response([
                'id' => '524126980791429', 'name' => 'Lucky Shrub', 'currency' => 'USD', 'timezone_id' => '1',
                'message_template_namespace' => 'ns_123', 'account_review_status' => 'APPROVED',
            ]),
        ]);

        $workspace = Workspace::create(['name' => 'W', 'slug' => 'w']);
        $user = User::factory()->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($user)->post('/onboarding/embedded-signup', [
            'code' => 'AQBhlXsct', 'event' => 'FINISH', 'waba_id' => '524126980791429',
            'phone_number_id' => '106540352242922', 'business_id' => '2729063490586005',
        ]);

        $account = WhatsAppAccount::where('waba_id', '524126980791429')->firstOrFail();

        $response->assertRedirect(route('accounts.show', $account));
        $this->assertSame('BUSINESS_TOKEN', $account->access_token);
        $this->assertSame('Lucky Shrub', $account->name);
        $this->assertSame('ns_123', $account->message_template_namespace);
        $this->assertNotNull($account->webhook_subscribed_at);
        $this->assertSame('completed', $account->onboarding_step);
        $this->assertSame('active', $account->status);
        $phone = $account->phoneNumbers()->where('phone_number_id', '106540352242922')->firstOrFail();
        $this->assertTrue($phone->is_registered);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $phone->two_step_pin);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/106540352242922/register') && $r['messaging_product'] === 'whatsapp' && strlen($r['pin']) === 6);
        $this->assertDatabaseHas('signup_sessions', ['waba_id' => '524126980791429', 'status' => 'completed']);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/oauth/access_token') && str_contains($r->url(), 'client_id=APP') && str_contains($r->url(), 'code=AQBhlXsct'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/524126980791429/subscribed_apps') && $r->hasHeader('Authorization', 'Bearer BUSINESS_TOKEN'));
    }

    public function test_phone_registration_posts_pin(): void
    {
        Http::fake(['graph.facebook.com/*/106540352242922/register' => Http::response(['success' => true])]);

        $workspace = Workspace::create(['name' => 'W', 'slug' => 'w']);
        $user = User::factory()->create(['workspace_id' => $workspace->id]);
        $account = WhatsAppAccount::create(['workspace_id' => $workspace->id, 'waba_id' => '1', 'access_token' => 'T', 'webhook_subscribed_at' => now(), 'onboarding_step' => 'webhook_subscribed']);
        $phone = $account->phoneNumbers()->create(['phone_number_id' => '106540352242922']);

        $this->actingAs($user)->post(route('phones.register', $phone), ['pin' => '581063'])->assertRedirect();

        $this->assertTrue($phone->fresh()->is_registered);
        $this->assertSame('581063', $phone->fresh()->two_step_pin);
        $this->assertSame('active', $account->fresh()->status);

        Http::assertSent(fn ($r) => $r['messaging_product'] === 'whatsapp' && $r['pin'] === '581063');
    }
}
