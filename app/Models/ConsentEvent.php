<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only consent ledger (opt-in / opt-out with proof). Never updated or deleted. */
class ConsentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['workspace_id', 'contact_id', 'wa_id', 'action', 'source', 'scope', 'keyword', 'consent_text', 'user_id'];

    protected function casts(): array
    {
        return ['scope' => 'array', 'created_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
