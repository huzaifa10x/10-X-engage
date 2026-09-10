<?php

namespace App\Services\Meta;

use App\Models\WhatsAppAccount;

/**
 * Cloud API collection — "Templates" folder + Business Management "Media" (Resumable Upload)
 * for template header example handles.
 */
class TemplateService
{
    public function __construct(protected GraphClient $client) {}

    public function for(WhatsAppAccount $account): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withToken($account->apiToken());

        return $clone;
    }

    protected const FIELDS = 'id,name,language,category,previous_category,status,rejected_reason,quality_score,parameter_format,components';

    /** "Get all templates" — GET /{{WABA-ID}}/message_templates (follows paging) */
    public function all(string $wabaId, int $limit = 100): array
    {
        $templates = [];
        $after = null;

        do {
            $query = ['fields' => self::FIELDS, 'limit' => $limit];
            if ($after) {
                $query['after'] = $after;
            }

            $page = $this->client->get("{$wabaId}/message_templates", $query);
            $templates = array_merge($templates, $page['data'] ?? []);
            $after = $page['paging']['cursors']['after'] ?? null;
            $hasNext = isset($page['paging']['next']);
        } while ($after && $hasNext);

        return $templates;
    }

    /** "Get template by name" — GET /{{WABA-ID}}/message_templates?name= */
    public function byName(string $wabaId, string $name): array
    {
        $response = $this->client->get("{$wabaId}/message_templates", ['name' => $name, 'fields' => self::FIELDS]);

        return $response['data'] ?? [];
    }

    /** "Get template by ID" — GET /<TEMPLATE_ID> */
    public function byId(string $templateId): array
    {
        return $this->client->get($templateId, ['fields' => self::FIELDS]);
    }

    /** "Get namespace" — GET /{{WABA-ID}}?fields=message_template_namespace */
    public function namespace(string $wabaId): ?string
    {
        return $this->client->get($wabaId, ['fields' => 'message_template_namespace'])['message_template_namespace'] ?? null;
    }

    /**
     * "Create template ..." — POST /{{WABA-ID}}/message_templates
     * { name, language, category, components[] }  =>  { id, status, category }
     */
    public function create(string $wabaId, array $template): array
    {
        return $this->client->post("{$wabaId}/message_templates", $template);
    }

    /** "Edit template" — POST /<TEMPLATE_ID> { components, category } => { success: true } */
    public function update(string $templateId, array $changes): array
    {
        return $this->client->post($templateId, $changes);
    }

    /** "Delete template by name" — DELETE /{{WABA-ID}}/message_templates?name= */
    public function deleteByName(string $wabaId, string $name): array
    {
        return $this->client->delete("{$wabaId}/message_templates", ['name' => $name]);
    }

    /** "Delete template by ID" — DELETE /{{WABA-ID}}/message_templates?hsm_id=&name= */
    public function deleteById(string $wabaId, string $templateId, string $name): array
    {
        return $this->client->delete("{$wabaId}/message_templates", ['hsm_id' => $templateId, 'name' => $name]);
    }

    /**
     * Resumable Upload API — used to obtain the `header_handle` for IMAGE / VIDEO / DOCUMENT headers.
     *
     * Step 1: POST /{{app-id}}/uploads?file_length=&file_type=&file_name=  => { id: "upload:..." }
     * Step 2: POST /{{upload-id}} with header file_offset: 0 and the raw bytes  => { h: "4::..." }
     */
    public function uploadHeaderMedia(string $contents, string $mime, string $fileName): string
    {
        $appId = config('whatsapp.app.id');

        $session = $this->client->post("{$appId}/uploads", [], [
            'file_length' => strlen($contents),
            'file_type' => $mime,
            'file_name' => $fileName,
        ]);

        $uploadId = $session['id'] ?? throw new \RuntimeException('Upload session was not created.');

        $result = $this->client->postRaw($uploadId, $contents, ['file_offset' => '0']);

        return $result['h'] ?? throw new \RuntimeException('Upload did not return a handle.');
    }
}
