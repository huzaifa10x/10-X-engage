<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Contact extends Model
{
    public const WINDOW_HOURS = 24;

    public const OPENED_BY_INBOUND = 'inbound';

    public const OPENED_BY_TEMPLATE = 'template';

    protected $fillable = [
        'workspace_id', 'wa_id', 'phone', 'name', 'email', 'company', 'tags', 'notes', 'source',
        'phone_number_id', 'created_by', 'last_inbound_at', 'last_outbound_at', 'last_message_at',
        'last_message_preview', 'last_message_direction', 'unread_count', 'window_expires_at', 'window_opened_by', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'tags' => 'array',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
            'last_message_at' => 'datetime',
            'window_expires_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* Conversation window ----------------------------------------------- */

    /** True while free-form (non-template) messages may be sent to this contact. */
    public function isWindowOpen(): bool
    {
        return $this->window_expires_at !== null && $this->window_expires_at->isFuture();
    }

    /** Opens / extends the 24-hour window. Inbound messages always open it; templates only when configured. */
    public function openWindow(string $openedBy, ?Carbon $from = null): void
    {
        $from ??= now();

        $this->forceFill([
            'window_expires_at' => $from->copy()->addHours(self::WINDOW_HOURS),
            'window_opened_by' => $openedBy,
        ])->save();
    }

    public function windowState(): array
    {
        $open = $this->isWindowOpen();

        return [
            'open' => $open,
            'expires_at' => $this->window_expires_at?->toIso8601String(),
            'opened_by' => $this->window_opened_by,
            'seconds_left' => $open ? max(0, now()->diffInSeconds($this->window_expires_at, false)) : 0,
            'has_history' => $this->last_message_at !== null,
        ];
    }

    /** Record the latest message on the conversation summary used by the inbox list. */
    public function touchConversation(Message $message): void
    {
        $this->forceFill([
            'last_message_at' => $message->created_at ?? now(),
            'last_message_preview' => $message->preview,
            'last_message_direction' => $message->direction,
            'phone_number_id' => $this->phone_number_id ?? $message->phone_number_id,
        ])->save();
    }

    /* Helpers ------------------------------------------------------------ */

    public static function normalizeWaId(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }

    public function displayName(): string
    {
        return $this->name ?: '+'.$this->wa_id;
    }

    public function initials(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return substr($this->wa_id, -2);
        }
        $parts = preg_split('/\s+/', $name);

        return strtoupper(substr($parts[0], 0, 1).substr($parts[1] ?? '', 0, 1));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $digits = self::normalizeWaId($term);

        return $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%");
            if ($digits !== '') {
                $q->orWhere('wa_id', 'like', "%{$digits}%");
            }
        });
    }
}
