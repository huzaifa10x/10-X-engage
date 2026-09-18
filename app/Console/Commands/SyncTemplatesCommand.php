<?php

namespace App\Console\Commands;

use App\Actions\Templates\SyncTemplates;
use App\Models\WhatsAppAccount;
use Illuminate\Console\Command;

class SyncTemplatesCommand extends Command
{
    protected $signature = 'engage:sync-templates {--account= : Only this whatsapp_accounts.id}';

    protected $description = 'Mirror message templates from Meta for every connected WABA (removes templates deleted on Meta)';

    public function handle(SyncTemplates $sync): int
    {
        $accounts = WhatsAppAccount::query()
            ->whereNotNull('access_token')
            ->when($this->option('account'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        foreach ($accounts as $account) {
            try {
                $n = $sync($account);
                $this->info("{$account->waba_id}: {$n} template(s) in sync");
            } catch (\Throwable $e) {
                $this->error("{$account->waba_id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
