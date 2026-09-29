<?php

namespace App\Actions\Broadcasts;

use App\Jobs\ProcessBroadcast;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Support\SegmentQuery;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots the audience into broadcast_recipients and queues the send. Idempotent: a broadcast
 * that already has recipients is not re-expanded.
 */
class LaunchBroadcast
{
    public function __invoke(Broadcast $broadcast): Broadcast
    {
        if (! in_array($broadcast->status, [Broadcast::STATUS_DRAFT, Broadcast::STATUS_SCHEDULED], true)) {
            return $broadcast;
        }

        DB::transaction(function () use ($broadcast) {
            if ($broadcast->recipients()->doesntExist()) {
                $segment = $broadcast->segment;
                [$matching, $eligible] = SegmentQuery::audience($broadcast->workspace_id, $segment?->rules, $segment?->match ?? 'all', $broadcast->require_opt_in);

                $eligibleIds = [];
                $eligible->select('id')->orderBy('id')->chunk(500, function ($chunk) use ($broadcast, &$eligibleIds) {
                    $rows = $chunk->map(fn ($c) => ['broadcast_id' => $broadcast->id, 'contact_id' => $c->id, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()])->all();
                    BroadcastRecipient::insert($rows);
                    $eligibleIds = array_merge($eligibleIds, $chunk->pluck('id')->all());
                });

                // Everyone who matched but was excluded by consent is recorded as skipped (visible in the report).
                $matching->select('id', 'opt_in_status')->whereNotIn('id', $eligibleIds)->orderBy('id')->chunk(500, function ($chunk) use ($broadcast) {
                    BroadcastRecipient::insert($chunk->map(fn ($c) => [
                        'broadcast_id' => $broadcast->id, 'contact_id' => $c->id, 'status' => 'skipped',
                        'skip_reason' => $c->opt_in_status === 'opted_out' ? 'opted_out' : ($broadcast->require_opt_in ? 'not_opted_in' : 'suppressed'),
                        'created_at' => now(), 'updated_at' => now(),
                    ])->all());
                });
            }

            $broadcast->forceFill(['status' => Broadcast::STATUS_QUEUED, 'started_at' => now(), 'error' => null])->save();
            $broadcast->refreshCounters();
        });

        ProcessBroadcast::dispatch($broadcast->id);

        return $broadcast->fresh();
    }
}
