<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phone_number_id')->constrained('phone_numbers')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wamid')->nullable()->unique();          // wamid.xxx returned by Meta
            $table->string('direction', 16);                         // outbound | inbound
            $table->string('type', 32);                              // text | template | image | document | video | audio | sticker | location | interactive | contacts | reaction
            $table->string('status', 32)->default('pending');        // pending | accepted | sent | delivered | read | failed | deleted | received
            $table->string('to', 32)->nullable();
            $table->string('from', 32)->nullable();
            $table->string('context_wamid')->nullable();
            $table->text('preview')->nullable();                     // human readable summary
            $table->json('payload')->nullable();                     // exact request body sent to /messages OR inbound message object
            $table->json('response')->nullable();                    // API response
            $table->string('conversation_id')->nullable();
            $table->string('conversation_origin', 32)->nullable();
            $table->string('pricing_category', 32)->nullable();
            $table->boolean('billable')->nullable();
            $table->integer('error_code')->nullable();
            $table->string('error_title')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_data')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'direction', 'status']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
