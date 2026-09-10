<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Media uploaded to Meta – either a Cloud API media ID (POST /{phone}/media)
        // or a Resumable Upload handle (POST /{app}/uploads) used for template header examples.
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained('phone_numbers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 32);                              // media_id | upload_handle
            $table->string('media_type', 32)->nullable();            // image | video | audio | document | sticker
            $table->string('meta_media_id')->nullable()->index();
            $table->text('upload_handle')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('sha256')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
