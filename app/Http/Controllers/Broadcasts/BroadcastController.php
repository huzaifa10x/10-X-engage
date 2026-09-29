<?php

namespace App\Http\Controllers\Broadcasts;

use App\Actions\Broadcasts\LaunchBroadcast;
use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\MessageTemplate;
use App\Models\PhoneNumber;
use App\Models\Segment;
use App\Support\SegmentQuery;
use App\Support\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function index(Request $request): Response
    {
        $broadcasts = $request->user()->workspace->broadcasts()->with(['template:id,name', 'phoneNumber:id,display_phone_number', 'segment:id,name'])
            ->latest()->paginate(20)->through(fn (Broadcast $b) => self::row($b));

        return Inertia::render('broadcasts/index', ['broadcasts' => $broadcasts]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('broadcasts/form', $this->formProps($request, null));
    }

    public function store(Request $request, LaunchBroadcast $launch): RedirectResponse
    {
        $data = $this->validated($request);
        $broadcast = $request->user()->workspace->broadcasts()->create($data + ['created_by' => $request->user()->id, 'status' => Broadcast::STATUS_DRAFT]);

        return $this->finish($request, $broadcast, $launch);
    }

    public function edit(Request $request, Broadcast $broadcast): Response
    {
        $this->own($request, $broadcast);
        abort_unless($broadcast->isEditable(), 403, 'Only draft or scheduled broadcasts can be edited.');

        return Inertia::render('broadcasts/form', $this->formProps($request, $broadcast));
    }

    public function update(Request $request, Broadcast $broadcast, LaunchBroadcast $launch): RedirectResponse
    {
        $this->own($request, $broadcast);
        abort_unless($broadcast->isEditable(), 403);
        $broadcast->update($this->validated($request));

        return $this->finish($request, $broadcast, $launch);
    }

    public function show(Request $request, Broadcast $broadcast): Response
    {
        $this->own($request, $broadcast);
        $broadcast->load(['template', 'phoneNumber', 'segment']);

        $recipients = $broadcast->recipients()->with('contact:id,name,wa_id,phone')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('id')->paginate(50)->withQueryString()
            ->through(fn (BroadcastRecipient $r) => [
                'id' => $r->id,
                'contact' => $r->contact ? ['id' => $r->contact->id, 'name' => $r->contact->name, 'phone' => $r->contact->phone ?? '+'.$r->contact->wa_id] : null,
                'status' => $r->status,
                'skip_reason' => $r->skip_reason,
                'error' => $r->error,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ]);

        $preview = $broadcast->template ? TemplateRenderer::render($broadcast->template, []) : null;

        return Inertia::render('broadcasts/show', [
            'broadcast' => self::row($broadcast) + ['template_params' => $broadcast->template_params, 'preview' => $preview, 'error' => $broadcast->error],
            'recipients' => $recipients,
            'filters' => $request->only('status'),
        ]);
    }

    /** JSON for live progress polling. */
    public function progress(Request $request, Broadcast $broadcast): JsonResponse
    {
        $this->own($request, $broadcast);

        return response()->json(self::row($broadcast->load(['template:id,name', 'phoneNumber:id,display_phone_number', 'segment:id,name'])));
    }

    public function launch(Request $request, Broadcast $broadcast, LaunchBroadcast $launch): RedirectResponse
    {
        $this->own($request, $broadcast);
        $launch($broadcast->forceFill(['scheduled_at' => null]));

        return redirect()->route('broadcasts.show', $broadcast)->with('success', 'Broadcast started.');
    }

    public function cancel(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->own($request, $broadcast);
        if (in_array($broadcast->status, [Broadcast::STATUS_COMPLETED, Broadcast::STATUS_FAILED], true)) {
            return back()->with('error', 'This broadcast has already finished.');
        }

        $broadcast->recipients()->where('status', 'queued')->whereNull('message_id')->update(['status' => 'skipped', 'skip_reason' => 'cancelled']);
        $broadcast->forceFill(['status' => Broadcast::STATUS_CANCELLED, 'completed_at' => now()])->save();
        $broadcast->refreshCounters();
        $broadcast->forceFill(['status' => Broadcast::STATUS_CANCELLED])->save();

        return back()->with('success', 'Broadcast cancelled. Messages already handed to Meta will still be delivered.');
    }

    public function destroy(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->own($request, $broadcast);
        abort_unless(in_array($broadcast->status, [Broadcast::STATUS_DRAFT, Broadcast::STATUS_SCHEDULED, Broadcast::STATUS_CANCELLED], true), 403);
        $broadcast->delete();

        return redirect()->route('broadcasts.index')->with('success', 'Broadcast deleted.');
    }

    /* ---------------------------------------------------------------- helpers */

    protected function finish(Request $request, Broadcast $broadcast, LaunchBroadcast $launch): RedirectResponse
    {
        return match ($request->input('action', 'draft')) {
            'send' => tap(redirect()->route('broadcasts.show', $broadcast)->with('success', 'Broadcast started.'), fn () => $launch($broadcast)),
            'schedule' => tap(redirect()->route('broadcasts.show', $broadcast)->with('success', 'Broadcast scheduled for '.$broadcast->scheduled_at?->format('d M Y H:i').'.'), fn () => $broadcast->forceFill(['status' => Broadcast::STATUS_SCHEDULED])->save()),
            default => redirect()->route('broadcasts.show', $broadcast)->with('success', 'Draft saved.'),
        };
    }

    protected function validated(Request $request): array
    {
        $workspaceId = $request->user()->workspace_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone_number_id' => ['required', 'integer', Rule::exists('phone_numbers', 'id')],
            'message_template_id' => ['required', 'integer', Rule::exists('message_templates', 'id')],
            'segment_id' => ['nullable', 'integer', Rule::exists('segments', 'id')->where('workspace_id', $workspaceId)],
            'require_opt_in' => ['nullable', 'boolean'],
            'template_params' => ['nullable', 'array'],
            'template_params.header' => ['nullable', 'array'],
            'template_params.body' => ['nullable', 'array'],
            'template_params.body.*' => ['nullable', 'string', 'max:1024'],
            'template_params.buttons' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date', Rule::requiredIf($request->input('action') === 'schedule')],
            'action' => ['nullable', Rule::in(['draft', 'send', 'schedule'])],
        ]);

        $phone = PhoneNumber::with('account')->findOrFail($data['phone_number_id']);
        abort_unless($phone->account->workspace_id === $workspaceId && $phone->is_registered, 422, 'Choose a registered number in your workspace.');

        $template = MessageTemplate::findOrFail($data['message_template_id']);
        abort_unless($template->whatsapp_account_id === $phone->whatsapp_account_id && $template->isSendable(), 422, 'Choose an APPROVED template of the selected number\'s account.');

        $data['require_opt_in'] = (bool) ($data['require_opt_in'] ?? config('whatsapp.consent.broadcast_requires_opt_in', false));
        $data['scheduled_at'] = ($request->input('action') === 'schedule' && ! empty($data['scheduled_at'])) ? $data['scheduled_at'] : null;
        unset($data['action']);

        return $data;
    }

    protected function formProps(Request $request, ?Broadcast $broadcast): array
    {
        $workspace = $request->user()->workspace;

        return [
            'broadcast' => $broadcast ? self::row($broadcast) + ['template_params' => $broadcast->template_params, 'scheduled_at' => $broadcast->scheduled_at?->toIso8601String()] : null,
            'phones' => $workspace->phoneNumbers()->where('is_registered', true)->get()->map(fn ($p) => [
                'id' => $p->id, 'account_id' => $p->whatsapp_account_id, 'display_phone_number' => $p->display_phone_number, 'verified_name' => $p->verified_name, 'is_default' => $p->is_default,
            ])->values(),
            'templates' => MessageTemplate::whereIn('whatsapp_account_id', $workspace->whatsappAccounts()->pluck('id'))->where('status', 'APPROVED')->orderBy('name')->get()->map(fn ($t) => [
                'id' => $t->id, 'account_id' => $t->whatsapp_account_id, 'name' => $t->name, 'language' => $t->language, 'category' => $t->category, 'components' => $t->components,
            ])->values(),
            'segments' => $workspace->segments()->orderBy('name')->get()->map(fn (Segment $s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'total_contacts' => $workspace->contacts()->count(),
            'tokens' => ['contact.name', 'contact.first_name', 'contact.phone', 'contact.email', 'contact.company'],
            'default_require_opt_in' => (bool) config('whatsapp.consent.broadcast_requires_opt_in', false),
        ];
    }

    protected function own(Request $request, Broadcast $broadcast): void
    {
        abort_unless($broadcast->workspace_id === $request->user()->workspace_id, 403);
    }

    public static function row(Broadcast $b): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'status' => $b->status,
            'phone_number_id' => $b->phone_number_id,
            'message_template_id' => $b->message_template_id,
            'segment_id' => $b->segment_id,
            'require_opt_in' => $b->require_opt_in,
            'template' => $b->relationLoaded('template') && $b->template ? ['id' => $b->template->id, 'name' => $b->template->name] : null,
            'sender' => $b->relationLoaded('phoneNumber') && $b->phoneNumber ? $b->phoneNumber->display_phone_number : null,
            'segment' => $b->relationLoaded('segment') && $b->segment ? $b->segment->name : ($b->segment_id ? null : 'All contacts'),
            'scheduled_at' => $b->scheduled_at?->toIso8601String(),
            'started_at' => $b->started_at?->toIso8601String(),
            'completed_at' => $b->completed_at?->toIso8601String(),
            'counts' => [
                'total' => $b->total_count, 'queued' => $b->queued_count, 'sent' => $b->sent_count, 'delivered' => $b->delivered_count,
                'read' => $b->read_count, 'failed' => $b->failed_count, 'skipped' => $b->skipped_count,
            ],
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }
}
