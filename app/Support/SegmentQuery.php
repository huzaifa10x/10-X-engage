<?php

namespace App\Support;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Compiles a segment rule tree into a contacts query.
 *
 * Rule: { field, op, value }. Fields: name, email, company, phone, tags, source, opt_in_status,
 * window (open|closed), created_at / last_activity_at / last_message_at (days), custom.<key> (custom_fields JSON).
 */
class SegmentQuery
{
    public const FIELDS = [
        'name' => 'text', 'email' => 'text', 'company' => 'text', 'phone' => 'text',
        'tags' => 'tags', 'source' => 'enum', 'opt_in_status' => 'enum', 'window' => 'window',
        'created_at' => 'days', 'last_activity_at' => 'days', 'last_message_at' => 'days', 'last_inbound_at' => 'days',
        'unread_count' => 'number',
    ];

    public const OPS = [
        'text' => ['eq', 'neq', 'contains', 'not_contains', 'starts_with', 'is_empty', 'is_not_empty'],
        'tags' => ['contains', 'not_contains', 'is_empty', 'is_not_empty'],
        'enum' => ['eq', 'neq'],
        'window' => ['eq'],
        'days' => ['within_days', 'older_than_days', 'is_empty', 'is_not_empty'],
        'number' => ['eq', 'gt', 'lt'],
    ];

    public static function apply(Builder $query, array $rules, string $match = 'all'): Builder
    {
        $rules = array_values(array_filter($rules, fn ($r) => ! empty($r['field'])));
        if ($rules === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($rules, $match) {
            foreach ($rules as $rule) {
                $method = $match === 'any' ? 'orWhere' : 'where';
                $q->{$method}(fn (Builder $inner) => self::rule($inner, $rule));
            }
        });
    }

    protected static function rule(Builder $q, array $rule): void
    {
        $field = (string) $rule['field'];
        $op = (string) ($rule['op'] ?? 'eq');
        $value = $rule['value'] ?? null;

        if (str_starts_with($field, 'custom.')) {
            $key = substr($field, 7);
            if (! preg_match('/^[a-z0-9_]+$/i', $key)) {
                throw new InvalidArgumentException("Invalid attribute key {$key}");
            }
            self::text($q, "custom_fields->{$key}", $op, $value, json: true);

            return;
        }

        $type = self::FIELDS[$field] ?? throw new InvalidArgumentException("Unknown segment field {$field}");

        match ($type) {
            'text' => self::text($q, $field, $op, $value),
            'tags' => self::tags($q, $op, $value),
            'enum' => $op === 'neq' ? $q->where($field, '!=', $value) : $q->where($field, $value),
            'window' => $value === 'open' ? $q->where('window_expires_at', '>', now()) : $q->where(fn ($w) => $w->whereNull('window_expires_at')->orWhere('window_expires_at', '<=', now())),
            'days' => self::days($q, $field, $op, (int) $value),
            'number' => $q->where($field, match ($op) { 'gt' => '>', 'lt' => '<', default => '=' }, (int) $value),
        };
    }

    protected static function text(Builder $q, string $column, string $op, mixed $value, bool $json = false): void
    {
        $value = (string) $value;
        match ($op) {
            'eq' => $q->where($column, $value),
            'neq' => $q->where(fn ($w) => $w->where($column, '!=', $value)->orWhereNull($column)),
            'contains' => $q->where($column, 'like', "%{$value}%"),
            'not_contains' => $q->where(fn ($w) => $w->where($column, 'not like', "%{$value}%")->orWhereNull($column)),
            'starts_with' => $q->where($column, 'like', "{$value}%"),
            'is_empty' => $q->where(fn ($w) => $w->whereNull($column)->orWhere($column, '')),
            'is_not_empty' => $q->whereNotNull($column)->where($column, '!=', ''),
            default => throw new InvalidArgumentException("Unsupported op {$op}"),
        };
    }

    protected static function tags(Builder $q, string $op, mixed $value): void
    {
        $tags = array_values(array_filter(array_map('trim', is_array($value) ? $value : explode(',', (string) $value))));
        match ($op) {
            'contains' => $q->where(function ($w) use ($tags) {
                foreach ($tags as $t) {
                    $w->orWhereJsonContains('tags', $t);
                }
            }),
            'not_contains' => $q->where(function ($w) use ($tags) {
                $w->whereNull('tags');
                foreach ($tags as $t) {
                    $w->orWhereJsonDoesntContain('tags', $t);
                }
            }),
            'is_empty' => $q->where(fn ($w) => $w->whereNull('tags')->orWhereJsonLength('tags', 0)),
            'is_not_empty' => $q->whereJsonLength('tags', '>', 0),
            default => throw new InvalidArgumentException("Unsupported op {$op}"),
        };
    }

    protected static function days(Builder $q, string $column, string $op, int $days): void
    {
        match ($op) {
            'within_days' => $q->where($column, '>=', now()->subDays($days)),
            'older_than_days' => $q->where($column, '<', now()->subDays($days)),
            'is_empty' => $q->whereNull($column),
            'is_not_empty' => $q->whereNotNull($column),
            default => throw new InvalidArgumentException("Unsupported op {$op}"),
        };
    }

    /** Audience for a broadcast: segment (or everyone) ∩ consent gate. Returns [match, eligible] builders. */
    public static function audience(int $workspaceId, ?array $rules, string $match, bool $requireOptIn): array
    {
        $base = Contact::where('workspace_id', $workspaceId);
        $matching = $rules === null ? clone $base : self::apply(clone $base, $rules, $match);

        $eligible = (clone $matching)
            ->where('opt_in_status', '!=', Contact::OPT_OUT)
            ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('suppression_list')->whereColumn('suppression_list.workspace_id', 'contacts.workspace_id')->whereColumn('suppression_list.wa_id', 'contacts.wa_id'));

        if ($requireOptIn) {
            $eligible->where('opt_in_status', Contact::OPT_IN);
        }

        return [$matching, $eligible];
    }
}
