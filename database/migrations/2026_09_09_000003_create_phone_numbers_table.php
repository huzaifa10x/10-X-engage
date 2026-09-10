<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number_id')->unique();                // {{Phone-Number-ID}}
            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('quality_rating', 16)->nullable();           // GREEN | YELLOW | RED | NA | UNKNOWN
            $table->string('name_status', 32)->nullable();              // APPROVED | DECLINED | EXPIRED | PENDING_REVIEW | NONE
            $table->string('new_name_status', 32)->nullable();
            $table->string('code_verification_status', 32)->nullable(); // VERIFIED | NOT_VERIFIED | EXPIRED
            $table->string('account_mode', 16)->nullable();             // SANDBOX | LIVE
            $table->string('messaging_limit_tier', 32)->nullable();     // TIER_50 ... TIER_UNLIMITED
            $table->string('platform_type', 32)->nullable();
            $table->boolean('is_official_business_account')->default(false);
            $table->boolean('is_registered')->default(false);
            $table->text('two_step_pin')->nullable();                   // encrypted
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('verification_code_requested_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('meta')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
