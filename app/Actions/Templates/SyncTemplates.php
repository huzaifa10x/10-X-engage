<?php

namespace App\Actions\Templates;

use App\Models\MessageTemplate;
use App\Models\WhatsAppAccount;
use App\Services\Meta\TemplateService;

/** Mirror GET /{{WABA-ID}}/message_templates into the message_templates table. */
class SyncTemplates
{
    public function __construct(protected TemplateService $templates) {}

    public function __invoke(WhatsAppAccount $account): int
    {
        $remote = $this->templates->for($account)->all($account->waba_id);
        $count = 0;

        foreach ($remote as $t) {
            self::upsert($account, $t);
            $count++;
        }

        if ($namespace = $this->templates->for($account)->namespace($account->waba_id)) {
            $account->update(['message_template_namespace' => $namespace]);
        }

        return $count;
    }

    public static function upsert(WhatsAppAccount $account, array $t): MessageTemplate
    {
        $template = MessageTemplate::firstOrNew([
            'whatsapp_account_id' => $account->id,
            'name' => $t['name'],
            'language' => $t['language'],
        ]);

        $template->fill([
            'template_id' => (string) ($t['id'] ?? $template->template_id),
            'category' => $t['category'] ?? $template->category ?? 'UTILITY',
            'previous_category' => $t['previous_category'] ?? null,
            'status' => $t['status'] ?? $template->status,
            'rejected_reason' => $t['rejected_reason'] ?? null,
            'quality_score' => $t['quality_score']['score'] ?? null,
            'parameter_format' => $t['parameter_format'] ?? null,
            'components' => $t['components'] ?? $template->components ?? [],
            'last_synced_at' => now(),
        ]);
        $template->save();

        return $template;
    }
}
