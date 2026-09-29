<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    public const KIND_MEDIA_ID = 'media_id';

    public const KIND_UPLOAD_HANDLE = 'upload_handle';

    protected $fillable = [
        'workspace_id', 'phone_number_id', 'user_id', 'kind', 'media_type', 'meta_media_id', 'upload_handle',
        'file_name', 'mime_type', 'file_size', 'sha256', 'expires_at', 'response',
        'message_id', 'direction', 'storage_disk', 'storage_path', 'downloaded_at', 'download_error',
    ];

    protected function casts(): array
    {
        return ['response' => 'array', 'expires_at' => 'datetime', 'downloaded_at' => 'datetime'];
    }

    public function isStored(): bool
    {
        return $this->storage_path !== null && $this->downloaded_at !== null;
    }

    /** Authenticated URL that streams the stored file (see MediaController::show). */
    public function url(): ?string
    {
        return $this->isStored() ? route('media.show', $this) : null;
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class);
    }
}
