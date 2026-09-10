<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\SendMessage;
use App\Exceptions\GraphApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PhoneNumber;
use App\Support\MessagePayloadBuilder;
use App\Support\TemplatePayloadBuilder;
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

        return Inertia::render('messages/create', [
            'phones' => $phones,
            'templates' => $templates,
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

        $builder = new MessagePayloadBuilder(preg_replace('/[^\d+]/', '', $data['to']), $data['reply_to'] ?? null);
        $template = null;

        try {
            [$payload, $preview] = match ($data['type']) {
                'text' => [
                    $builder->text($data['text']['body'], (bool) ($data['text']['preview_url'] ?? false)),
                    $data['text']['body'],
                ],
                'image', 'video', 'document', 'audio', 'sticker' => [
                    $builder->media(
                        $data['type'],
                        ($data['media']['source'] ?? 'link') === 'id' ? ($data['media']['id'] ?? null) : null,
                        ($data['media']['source'] ?? 'link') === 'link' ? ($data['media']['link'] ?? null) : null,
                        $data['media']['caption'] ?? null,
                        $data['media']['filename'] ?? null,
                    ),
                    ucfirst($data['type']).(($data['media']['caption'] ?? '') !== '' ? ': '.$data['media']['caption'] : ''),
                ],
                'location' => [
                    $builder->location($data['location']['latitude'], $data['location']['longitude'], $data['location']['name'] ?? null, $data['location']['address'] ?? null),
                    'Location: '.($data['location']['name'] ?? "{$data['location']['latitude']},{$data['location']['longitude']}"),
                ],
                'interactive' => $this->interactive($builder, $data['interactive'] ?? []),
                'template' => $this->templatePayload($builder, $data['template'], $phone, $template),
            };
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['type' => $e->getMessage()]);
        }

        try {
            $message = $send($request->user(), $phone, $payload, $preview, $template);
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

    protected function interactive(MessagePayloadBuilder $builder, array $i): array
    {
        if (($i['kind'] ?? 'button') === 'list') {
            $sections = array_values(array_filter($i['sections'] ?? [], fn ($s) => ! empty($s['rows'])));

            return [
                $builder->list($i['body'], $i['button_text'] ?? 'Choose', $sections, $i['header'] ?? null, $i['footer'] ?? null),
                'List: '.$i['body'],
            ];
        }

        return [
            $builder->replyButtons($i['body'], $i['buttons'] ?? [], $i['header'] ?? null, $i['footer'] ?? null),
            'Buttons: '.$i['body'],
        ];
    }

    protected function templatePayload(MessagePayloadBuilder $builder, array $input, PhoneNumber $phone, ?MessageTemplate &$template): array
    {
        $template = MessageTemplate::findOrFail($input['id']);

        if ($template->whatsapp_account_id !== $phone->whatsapp_account_id) {
            throw new \InvalidArgumentException('This template belongs to a different WhatsApp Business Account.');
        }
        if (! $template->isSendable()) {
            throw new \InvalidArgumentException("Template {$template->name} is {$template->status}; only APPROVED templates can be sent.");
        }

        $components = MessagePayloadBuilder::templateComponents($template, $input);

        return [
            $builder->template($template->name, $template->language, $components),
            'Template '.$template->name.': '.TemplatePayloadBuilder::summarize($template->components),
        ];
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
