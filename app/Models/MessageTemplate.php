<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    protected $fillable = [
        'whatsapp_account_id', 'template_id', 'name', 'language', 'category', 'previous_category', 'status',
        'rejected_reason', 'quality_score', 'parameter_format', 'components', 'last_response',
        'last_status_update_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'last_response' => 'array',
            'last_status_update_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function isSendable(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function component(string $type): ?array
    {
        foreach ($this->components ?? [] as $component) {
            if (strtoupper($component['type'] ?? '') === strtoupper($type)) {
                return $component;
            }
        }

        return null;
    }

    public function bodyText(): ?string
    {
        return $this->component('BODY')['text'] ?? null;
    }
}
