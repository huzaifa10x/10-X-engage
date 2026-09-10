<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\SyncTemplates;
use App\Exceptions\GraphApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Models\MessageTemplate;
use App\Models\WhatsAppAccount;
use App\Services\Meta\TemplateService;
use App\Support\TemplatePayloadBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;
        $accountIds = $workspace->whatsappAccounts()->pluck('id');

        $query = MessageTemplate::whereIn('whatsapp_account_id', $accountIds)->with('account:id,waba_id,name')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($accountId = $request->query('account')) {
            $query->where('whatsapp_account_id', $accountId);
        }

        return Inertia::render('templates/index', [
            'templates' => $query->paginate(20)->withQueryString()->through(fn ($t) => $this->row($t)),
            'accounts' => $workspace->whatsappAccounts()->get(['id', 'waba_id', 'name']),
            'filters' => $request->only('status', 'category', 'account'),
        ]);
    }

    public function create(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        return Inertia::render('templates/create', [
            'accounts' => $workspace->whatsappAccounts()->where('status', '!=', WhatsAppAccount::STATUS_DISCONNECTED)->get(['id', 'waba_id', 'name']),
            'options' => [
                'categories' => config('whatsapp.templates.categories'),
                'languages' => config('whatsapp.templates.languages'),
                'header_formats' => config('whatsapp.templates.header_formats'),
            ],
        ]);
    }

    /** POST /{{WABA-ID}}/message_templates */
    public function store(StoreTemplateRequest $request, TemplateService $templates): RedirectResponse
    {
        $data = $request->validated();
        $account = WhatsAppAccount::findOrFail($data['whatsapp_account_id']);
        Gate::authorize('update', $account);

        try {
            $payload = TemplatePayloadBuilder::build($data);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['body.text' => $e->getMessage()]);
        }

        if (MessageTemplate::where('whatsapp_account_id', $account->id)->where('name', $payload['name'])->where('language', $payload['language'])->exists()) {
            throw ValidationException::withMessages(['name' => 'A template with this name and language already exists on this account.']);
        }

        try {
            $result = $templates->for($account)->create($account->waba_id, $payload);
        } catch (GraphApiException $e) {
            return back()->withInput()->with('error', 'Meta rejected the template: '.$e->displayMessage());
        }

        $template = MessageTemplate::create([
            'whatsapp_account_id' => $account->id,
            'template_id' => (string) ($result['id'] ?? ''),
            'name' => $payload['name'],
            'language' => $payload['language'],
            'category' => $result['category'] ?? $payload['category'],
            'status' => strtoupper($result['status'] ?? 'PENDING'),
            'components' => $payload['components'],
            'last_response' => $result,
            'last_status_update_at' => now(),
        ]);

        return redirect()->route('templates.show', $template)->with('success', "Template submitted — status {$template->status}.");
    }

    public function show(Request $request, MessageTemplate $template): Response
    {
        Gate::authorize('view', $template->account);

        return Inertia::render('templates/show', ['template' => $this->row($template) + [
            'components' => $template->components,
            'last_response' => $template->last_response,
            'rejected_reason' => $template->rejected_reason,
            'quality_score' => $template->quality_score,
            'previous_category' => $template->previous_category,
            'last_synced_at' => $template->last_synced_at?->toIso8601String(),
            'last_status_update_at' => $template->last_status_update_at?->toIso8601String(),
        ]]);
    }

    /** Refresh a single template's status from GET /<TEMPLATE_ID>. */
    public function refresh(Request $request, MessageTemplate $template, TemplateService $templates): RedirectResponse
    {
        Gate::authorize('view', $template->account);

        try {
            $remote = $template->template_id
                ? $templates->for($template->account)->byId($template->template_id)
                : ($templates->for($template->account)->byName($template->account->waba_id, $template->name)[0] ?? null);
        } catch (GraphApiException $e) {
            return back()->with('error', $e->displayMessage());
        }

        if ($remote) {
            SyncTemplates::upsert($template->account, $remote);
        }

        return back()->with('success', 'Template status refreshed.');
    }

    /** Sync every template for an account from GET /{{WABA-ID}}/message_templates. */
    public function sync(Request $request, WhatsAppAccount $account, SyncTemplates $sync): RedirectResponse
    {
        Gate::authorize('view', $account);

        try {
            $count = $sync($account);
        } catch (GraphApiException $e) {
            return back()->with('error', 'Sync failed: '.$e->displayMessage());
        }

        return back()->with('success', "Synced {$count} template(s) from Meta.");
    }

    /** DELETE /{{WABA-ID}}/message_templates?hsm_id=&name= */
    public function destroy(Request $request, MessageTemplate $template, TemplateService $templates): RedirectResponse
    {
        Gate::authorize('update', $template->account);

        try {
            if ($template->template_id) {
                $templates->for($template->account)->deleteById($template->account->waba_id, $template->template_id, $template->name);
            } else {
                $templates->for($template->account)->deleteByName($template->account->waba_id, $template->name);
            }
        } catch (GraphApiException $e) {
            return back()->with('error', 'Delete failed: '.$e->displayMessage());
        }

        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template deleted.');
    }

    protected function row(MessageTemplate $t): array
    {
        return [
            'id' => $t->id,
            'template_id' => $t->template_id,
            'name' => $t->name,
            'language' => $t->language,
            'category' => $t->category,
            'status' => $t->status,
            'body' => $t->bodyText(),
            'account' => $t->account ? ['id' => $t->account->id, 'waba_id' => $t->account->waba_id, 'name' => $t->account->name] : null,
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }
}
