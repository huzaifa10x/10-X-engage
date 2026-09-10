<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = ['workspace_id', 'wa_id', 'name', 'last_inbound_at', 'last_outbound_at', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'last_inbound_at' => 'datetime', 'last_outbound_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public static function normalizeWaId(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }
}
