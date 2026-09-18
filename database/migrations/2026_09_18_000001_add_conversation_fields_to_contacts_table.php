<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts become first-class CRM records and carry the conversation state used by the inbox:
 * the 24-hour customer-service window, unread counter and last-message summary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('wa_id');            // E.164 with +
            $table->string('email')->nullable()->after('name');
            $table->string('company')->nullable()->after('email');
            $table->json('tags')->nullable()->after('company');
            $table->text('notes')->nullable()->after('tags');
            $table->string('source', 32)->default('manual')->after('notes');    // manual | inbound | outbound | import
            $table->foreignId('phone_number_id')->nullable()->after('source')->constrained('phone_numbers')->nullOnDelete(); // preferred sender
            $table->foreignId('created_by')->nullable()->after('phone_number_id')->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable()->after('last_outbound_at');
            $table->text('last_message_preview')->nullable()->after('last_message_at');
            $table->string('last_message_direction', 16)->nullable()->after('last_message_preview');
            $table->unsignedInteger('unread_count')->default(0)->after('last_message_direction');
            $table->timestamp('window_expires_at')->nullable()->after('unread_count');   // 24h customer-service window
            $table->string('window_opened_by', 16)->nullable()->after('window_expires_at'); // inbound | template
            $table->index(['workspace_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'last_message_at']);
            $table->dropConstrainedForeignId('phone_number_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['phone', 'email', 'company', 'tags', 'notes', 'source', 'last_message_at', 'last_message_preview', 'last_message_direction', 'unread_count', 'window_expires_at', 'window_opened_by']);
        });
    }
};
