<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = ['object', 'waba_id', 'field', 'signature', 'payload', 'status', 'error', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
