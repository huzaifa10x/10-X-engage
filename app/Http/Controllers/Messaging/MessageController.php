<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\SendMessage;
use App\Exceptions\GraphApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PhoneNumber;
use App\Support\ConversationGuard;
use App\Support\OutboundMessageFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        $query = $workspace->messages()->with(['phoneNumber:id,display_phone_number,verified_name', 'contact:id,wa_id,name'])->latest();

        if ($direction = $request->query('direction')) {
            $query->where('direction', $direction);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('to', 'like', "%{$search}%")->orWhere('from', 'like', "%{$search}%")->orWhere('preview', 'like', "%{$search}%"));
        }

        $messages = $query->paginate(25)->withQueryString()->through(fn (Message $m) => $this->row($m));

        return Inertia::render('messages/index', [
            'messages' => $messages,
            'filters' => $request->only('direction', 'status', 'q'),
            'stats' => [
                'sent' => $workspace->messages()->where('direction', 'outbound')->count(),
                'delivered' => $workspace->messages()->whereIn('status', ['delivered', 'read'])->count(),
                'failed' => $workspace->messages()->where('status', 'failed')->count(),
                'received' => $workspace->messages()->where('direction', 'inbound')->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        $phones = $workspace->phoneNumbers()
            ->with('account:id,waba_id,name,status')
            ->get()
            ->map(fn (PhoneNumber $p) => [
                'id' => $p->id,
                'phone_number_id' => $p->phone_number_id,
                'display_phone_number' => $p->display_phone_number,
                'verified_name' => $p->verified_name,
                'is_registered' => $p->is_registered,
                'is_default' => $p->is_default,
                'account_id' => $p->account->id,
                'waba_id' => $p->account->waba_id,
                'account_name' => $p->account->name,
            ]);

        $templates = MessageTemplate::whereIn('whatsapp_account_id', $workspace->whatsappAccounts()->pluck('id'))
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get()
            ->map(fn (MessageTemplate $t) => [
                'id' => $t->id,
                'account_id' => $t->whatsapp_account_id,
                'name' => $t->name,
                'language' => $t->language,
                'category' => $t->category,
                'components' => $t->components,
            ]);

        $contacts = $workspace->contacts()->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id,
            'wa_id' => $c->wa_id,
            'name' => $c->name,
            'window' => $c->windowState(),
        ]);

        return Inertia::render('messages/create', [
            'phones' => $phones,
            'templates' => $templates,
            'contacts' => $contacts,
            'reply_to' => $request->query('reply_to'),
            'to' => $request->query('to'),
        ]);
    }

    public function store(SendMessageRequest $request, SendMessage $send): RedirectResponse
    {
        $data = $request->validated();
        $phone = PhoneNumber::with('account')->findOrFail($data['phone_number_id']);

        Gate::authorize('update', $phone->account);

        if (! $phone->is_registered) {
            throw ValidationException::withMessages(['phone_number_id' => 'This phone number is not registered for Cloud API yet.']);
        }

        $contact = ConversationGuard::resolveContact($request->user()->workspace_id, $data['to']);
        ConversationGuard::assertCanSend($contact, $data['type']);

        try {
            [$payload, $preview, $template] = OutboundMessageFactory::build($data, $phone, $data['to'], $data['reply_to'] ?? null);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['type' => $e->getMessage()]);
        }

        try {
            $message = $send($request->user(), $phone, $payload, $preview, $template, $contact);
        } catch (GraphApiException $e) {
            return back()->withInput()->with('error', 'Meta rejected the message: '.$e->displayMessage());
        }

        return redirect()->route('messages.show', $message)->with('success', 'Message accepted by WhatsApp (id '.$message->wamid.').');
    }

    public function show(Request $request, Message $message): Response
    {
        Gate::authorize('view', $message);

        $message->load(['phoneNumber', 'contact', 'template']);

        return Inertia::render('messages/show', [
            'message' => $this->row($message) + [
                'payload' => $message->payload,
                'response' => $message->response,
                'error_data' => $message->error_data,
                'conversation_id' => $message->conversation_id,
                'conversation_origin' => $message->conversation_origin,
                'pricing_category' => $message->pricing_category,
                'billable' => $message->billable,
                'template' => $message->template ? ['id' => $message->template->id, 'name' => $message->template->name, 'language' => $message->template->language] : null,
            ],
        ]);
    }

    protected function row(Message $m): array
    {
        return [
            'id' => $m->id,
            'wamid' => $m->wamid,
            'direction' => $m->direction,
            'type' => $m->type,
            'status' => $m->status,
            'to' => $m->to,
            'from' => $m->from,
            'preview' => $m->preview,
            'contact' => $m->contact ? ['id' => $m->contact->id, 'wa_id' => $m->contact->wa_id, 'name' => $m->contact->name] : null,
            'phone' => $m->phoneNumber ? ['id' => $m->phoneNumber->id, 'display_phone_number' => $m->phoneNumber->display_phone_number, 'verified_name' => $m->phoneNumber->verified_name] : null,
            'error_code' => $m->error_code,
            'error_title' => $m->error_title,
            'error_message' => $m->error_message,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'delivered_at' => $m->delivered_at?->toIso8601String(),
            'read_at' => $m->read_at?->toIso8601String(),
            'failed_at' => $m->failed_at?->toIso8601String(),
            'received_at' => $m->received_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
