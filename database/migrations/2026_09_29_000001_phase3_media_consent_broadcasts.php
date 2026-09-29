<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: inbound media storage, consent (opt-in / suppression), queued outbound + broadcasts, segments.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* Media: keep our own copy of every file (Meta media URLs expire) ------------------ */
        Schema::table('media_assets', function (Blueprint $table) {
            $table->foreignId('message_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('direction', 16)->default('outbound')->after('kind'); // inbound | outbound
            $table->string('storage_disk', 32)->nullable()->after('response');
            $table->string('storage_path')->nullable()->after('storage_disk');
            $table->timestamp('downloaded_at')->nullable()->after('storage_path');
            $table->text('download_error')->nullable()->after('downloaded_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('media_asset_id')->nullable()->after('message_template_id')->constrained('media_assets')->nullOnDelete();
            $table->foreignId('broadcast_id')->nullable()->after('media_asset_id')->index();
            $table->string('origin', 16)->default('agent')->after('broadcast_id'); // agent | api | broadcast | automation | system
            $table->index(['workspace_id', 'direction', 'created_at']);
        });

        /* Consent ---------------------------------------------------------------------------- */
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('opt_in_status', 16)->default('unknown')->after('notes'); // unknown | opted_in | opted_out
            $table->json('opt_in_scope')->nullable()->after('opt_in_status');        // ['marketing','transactional']
            $table->timestamp('opted_at')->nullable()->after('opt_in_scope');
            $table->timestamp('last_activity_at')->nullable()->after('opted_at');
            $table->json('custom_fields')->nullable()->after('last_activity_at');        // custom fields
            $table->index(['workspace_id', 'opt_in_status']);
        });

        Schema::create('consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wa_id', 32)->index();
            $table->string('action', 16);                       // opt_in | opt_out
            $table->string('source', 32);                       // keyword | ui | import | api | form
            $table->json('scope')->nullable();
            $table->string('keyword', 64)->nullable();
            $table->text('consent_text')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();     // append-only: no updated_at
        });

        Schema::create('suppression_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('wa_id', 32);
            $table->string('reason', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'wa_id']);
        });

        /* Segments & broadcasts --------------------------------------------------------------- */
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('match', 8)->default('all');       // all | any
            $table->json('rules');                             // [{field, op, value}]
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phone_number_id')->constrained('phone_numbers')->cascadeOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('status', 16)->default('draft');    // draft | scheduled | queued | sending | completed | cancelled | failed
            $table->json('template_params')->nullable();       // header/body/buttons with {{contact.*}} tokens
            $table->json('audience')->nullable();              // snapshot: {segment_id|all, require_opt_in, counts}
            $table->boolean('require_opt_in')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('queued');   // queued | sent | delivered | read | failed | skipped
            $table->string('skip_reason', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['broadcast_id', 'contact_id']);
            $table->index(['broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('suppression_list');
        Schema::dropIfExists('consent_events');
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'opt_in_status']);
            $table->dropColumn(['opt_in_status', 'opt_in_scope', 'opted_at', 'last_activity_at', 'custom_fields']);
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'direction', 'created_at']);
            $table->dropConstrainedForeignId('media_asset_id');
            $table->dropColumn(['broadcast_id', 'origin']);
        });
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('message_id');
            $table->dropColumn(['direction', 'storage_disk', 'storage_path', 'downloaded_at', 'download_error']);
        });
    }
};
