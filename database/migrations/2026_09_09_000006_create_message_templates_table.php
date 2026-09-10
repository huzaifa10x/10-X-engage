<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->string('template_id')->nullable()->index();     // Meta template (HSM) id
            $table->string('name');
            $table->string('language', 16);
            $table->string('category', 32);                          // MARKETING | UTILITY | AUTHENTICATION
            $table->string('previous_category', 32)->nullable();
            $table->string('status', 32)->default('PENDING');        // APPROVED | PENDING | REJECTED | PAUSED | DISABLED | IN_APPEAL | ...
            $table->string('rejected_reason')->nullable();
            $table->string('quality_score', 32)->nullable();
            $table->string('parameter_format', 16)->nullable();      // POSITIONAL | NAMED
            $table->json('components');                              // exact components array sent to / returned from Meta
            $table->json('last_response')->nullable();
            $table->timestamp('last_status_update_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['whatsapp_account_id', 'name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
