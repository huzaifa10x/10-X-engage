<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('object', 64)->nullable();     // whatsapp_business_account
            $table->string('waba_id')->nullable()->index();
            $table->string('field', 64)->nullable()->index(); // messages, message_template_status_update, account_update ...
            $table->string('signature')->nullable();
            $table->json('payload');
            $table->string('status', 32)->default('received'); // received | processed | failed | ignored
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
