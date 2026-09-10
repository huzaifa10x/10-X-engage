<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per WhatsApp Business Account (WABA) shared with us through Embedded Signup.
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('waba_id')->unique();                       // {{Assigned-WABA-ID}}
            $table->string('name')->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('timezone_id', 16)->nullable();
            $table->string('message_template_namespace')->nullable();
            $table->string('business_id')->nullable();                 // customer's business portfolio ID
            $table->string('owner_business_name')->nullable();
            $table->string('account_review_status', 32)->nullable();   // PENDING | APPROVED | REJECTED
            $table->string('ban_state', 32)->nullable();
            $table->string('primary_funding_id')->nullable();

            // Business token returned by GET /oauth/access_token (encrypted at rest)
            $table->text('access_token')->nullable();
            $table->string('token_type', 32)->nullable();               // USER | SYSTEM | BUSINESS
            $table->json('token_scopes')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('token_debugged_at')->nullable();

            $table->timestamp('webhook_subscribed_at')->nullable();
            $table->string('webhook_override_callback_uri')->nullable();
            $table->timestamp('system_user_assigned_at')->nullable();
            $table->string('credit_allocation_config_id')->nullable();
            $table->timestamp('credit_line_shared_at')->nullable();

            // pending_setup | active | disabled | disconnected
            $table->string('status', 32)->default('pending_setup');
            $table->string('onboarding_step', 64)->default('token_exchanged');
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
