<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneNumber extends Model
{
    protected $fillable = [
        'whatsapp_account_id', 'phone_number_id', 'display_phone_number', 'verified_name', 'quality_rating',
        'name_status', 'new_name_status', 'code_verification_status', 'account_mode', 'messaging_limit_tier',
        'platform_type', 'is_official_business_account', 'is_registered', 'two_step_pin', 'registered_at',
        'verification_code_requested_at', 'is_default', 'meta', 'last_synced_at',
    ];

    protected $hidden = ['two_step_pin'];

    protected function casts(): array
    {
        return [
            'two_step_pin' => 'encrypted',
            'is_official_business_account' => 'boolean',
            'is_registered' => 'boolean',
            'is_default' => 'boolean',
            'meta' => 'array',
            'registered_at' => 'datetime',
            'verification_code_requested_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    /** Digits-only version of the display number (webhooks use this format). */
    public function normalizedNumber(): string
    {
        return preg_replace('/\D+/', '', (string) $this->display_phone_number) ?? '';
    }
}
