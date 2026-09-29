<?php

namespace App\Console\Commands;

use App\Actions\Broadcasts\LaunchBroadcast;
use App\Models\Broadcast;
use Illuminate\Console\Command;

class DispatchBroadcasts extends Command
{
    protected $signature = 'engage:dispatch-broadcasts';

    protected $description = 'Start scheduled broadcasts whose scheduled_at has passed';

    public function handle(LaunchBroadcast $launch): int
    {
        $due = Broadcast::where('status', Broadcast::STATUS_SCHEDULED)->where('scheduled_at', '<=', now())->get();

        foreach ($due as $broadcast) {
            $launch($broadcast);
            $this->info("Launched broadcast #{$broadcast->id} {$broadcast->name}");
        }

        return self::SUCCESS;
    }
}
