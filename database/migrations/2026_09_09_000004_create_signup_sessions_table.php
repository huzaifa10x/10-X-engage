<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit trail of every Embedded Signup "message" event (FINISH / CANCEL / ERROR) and code exchange.
        Schema::create('signup_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 64)->nullable();          // FINISH, FINISH_ONLY_WABA, CANCEL, ERROR ...
            $table->string('waba_id')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('business_id')->nullable();
            $table->string('current_step', 64)->nullable();   // abandoned screen
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->string('meta_session_id')->nullable();
            $table->string('status', 32)->default('received'); // received | exchanged | completed | failed
            $table->text('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('code_exchanged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_sessions');
    }
};
