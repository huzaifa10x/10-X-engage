<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id', 'phone_number_id', 'message_template_id', 'segment_id', 'created_by', 'name', 'status',
        'template_params', 'audience', 'require_opt_in', 'scheduled_at', 'started_at', 'completed_at',
        'total_count', 'queued_count', 'sent_count', 'delivered_count', 'read_count', 'failed_count', 'skipped_count', 'error',
    ];

    protected function casts(): array
    {
        return [
            'template_params' => 'array', 'audience' => 'array', 'require_opt_in' => 'boolean',
            'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_SENDING], true);
    }

    /** Recompute the counters from recipient rows (called after each status change). */
    public function refreshCounters(): void
    {
        $counts = $this->recipients()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $this->forceFill([
            'total_count' => $counts->sum(),
            'queued_count' => $counts['queued'] ?? 0,
            'sent_count' => ($counts['sent'] ?? 0) + ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0),
            'delivered_count' => ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0),
            'read_count' => $counts['read'] ?? 0,
            'failed_count' => $counts['failed'] ?? 0,
            'skipped_count' => $counts['skipped'] ?? 0,
        ]);

        if ($this->isRunning() && ($counts['queued'] ?? 0) === 0) {
            $this->status = self::STATUS_COMPLETED;
            $this->completed_at = now();
        }

        $this->save();
    }
}
