<?php

namespace App\Actions\Templates;

use App\Models\MessageTemplate;
use App\Models\WhatsAppAccount;
use App\Services\Meta\TemplateService;

/** Mirror GET /{{WABA-ID}}/message_templates into the message_templates table. */
class SyncTemplates
{
    public function __construct(protected TemplateService $templates) {}

    /** @return int number of templates now mirrored (deleted ones are removed locally) */
    public function __invoke(WhatsAppAccount $account): int
    {
        $remote = $this->templates->for($account)->all($account->waba_id);
        $count = 0;
        $seen = [];

        foreach ($remote as $t) {
            if (strtoupper($t['status'] ?? '') === 'DELETED') {
                continue; // Meta keeps deleted templates in the list for a while; we drop them below
            }
            $seen[] = self::upsert($account, $t)->id;
            $count++;
        }

        // Anything Meta no longer returns (deleted in WhatsApp Manager / via API) is removed here too.
        // Messages keep their rows: messages.message_template_id is nullOnDelete.
        MessageTemplate::where('whatsapp_account_id', $account->id)->whereNotIn('id', $seen)->delete();

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
