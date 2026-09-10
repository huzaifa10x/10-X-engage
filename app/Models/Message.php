<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_INBOUND = 'inbound';

    protected $fillable = [
        'workspace_id', 'phone_number_id', 'contact_id', 'message_template_id', 'user_id', 'wamid', 'direction',
        'type', 'status', 'to', 'from', 'context_wamid', 'preview', 'payload', 'response', 'conversation_id',
        'conversation_origin', 'pricing_category', 'billable', 'error_code', 'error_title', 'error_message',
        'error_data', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'error_data' => 'array',
            'billable' => 'boolean',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
