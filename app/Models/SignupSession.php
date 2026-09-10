<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignupSession extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'whatsapp_account_id', 'event', 'waba_id', 'phone_number_id', 'business_id',
        'current_step', 'error_code', 'error_message', 'meta_session_id', 'status', 'failure_reason', 'payload',
        'code_exchanged_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'code_exchanged_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }
}
