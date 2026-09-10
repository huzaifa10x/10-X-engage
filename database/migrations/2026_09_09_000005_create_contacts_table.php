<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('wa_id', 32);                 // WhatsApp ID (E.164 digits, no +)
            $table->string('name')->nullable();          // profile.name from webhooks
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'wa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
