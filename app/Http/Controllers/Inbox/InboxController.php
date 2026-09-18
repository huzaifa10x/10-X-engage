<?php

namespace App\Http\Controllers\Inbox;

use App\Actions\Messaging\SendMessage;
use App\Exceptions\GraphApiException;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Controller;
use App\Http\Requests\InboxSendRequest;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PhoneNumber;
use App\Services\Meta\MessagingService;
use App\Support\ConversationGuard;
use App\Support\OutboundMessageFactory;
use App\Support\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WhatsApp-style two-pane inbox. The page is rendered once with Inertia; afterwards the
 * client polls the JSON endpoints below (conversations / messages) for live updates.
 */
class InboxController extends Controller
{
    public const PAGE_SIZE = 50;

    public function index(Request $request): Response
    {
        return $this->render($request, null);
    }

    public function show(Request $request, Contact $contact): Response
    {
        Gate::authorize('view', $contact);

        return $this->render($request, $contact->reconcileWindow());
    }

    protected function render(Request $request, ?Contact $contact): Response
    {
        $workspace = $request->user()->workspace;

        $phones = $workspace->phoneNumbers()->where('is_registered', true)->with('account:id,name')
            ->get()
            ->map(fn (PhoneNumber $p) => [
                'id' => $p->id,
                'account_id' => $p->whatsapp_account_id,
                'display_phone_number' => $p->display_phone_number,
                'verified_name' => $p->verified_name,
                'is_default' => $p->is_default,
            ])->values();

        $templates = MessageTemplate::whereIn('whatsapp_account_id', $workspace->whatsappAccounts()->pluck('id'))
            ->where('status', 'APPROVED')->orderBy('name')->get()
            ->map(fn (MessageTemplate $t) => [
                'id' => $t->id,
                'account_id' => $t->whatsapp_account_id,
                'name' => $t->name,
                'language' => $t->language,
                'category' => $t->category,
                'components' => $t->components,
            ])->values();

        return Inertia::render('inbox/index', [
            'conversations' => $this->conversationList($workspace->id, $request->query('q')),
            'phones' => $phones,
            'templates' => $templates,
            'selected' => $contact ? $this->conversationPayload($contact) : null,
            'filters' => $request->only('q'),
            'poll_interval_ms' => (int) config('whatsapp.inbox.poll_interval_ms', 4000),
        ]);
    }

    /* JSON: live updates ----------------------------------------------------- */

    /** Conversation list (left pane). */
    public function conversations(Request $request): JsonResponse
    {
        return response()->json([
            'conversations' => $this->conversationList($request->user()->workspace_id, $request->query('q')),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Messages for one contact. With ?after=<id> only newer messages are returned; with ?since=<iso>
     * messages whose status changed since then are returned too, so ticks update live.
     */
    public function messages(Request $request, Contact $contact): JsonResponse
    {
        Gate::authorize('view', $contact);

        $contact->reconcileWindow();

        $after = (int) $request->query('after', 0);
        $since = $request->query('since') ? Carbon::parse($request->query('since')) : null;
        $before = (int) $request->query('before', 0);

        $query = $contact->messages()->orderBy('id');

        if ($before > 0) {
            $older = $contact->messages()->where('id', '<', $before)->orderByDesc('id')->limit(self::PAGE_SIZE)->get()->reverse()->values();

            return response()->json(['messages' => $older->map(fn ($m) => self::messageRow($m)), 'has_more' => $older->count() === self::PAGE_SIZE]);
        }

        if ($after > 0 || $since) {
            $query->where(function ($q) use ($after, $since) {
                $q->where('id', '>', $after);
                if ($since) {
                    $q->orWhere('updated_at', '>', $since);
                }
            });
            $messages = $query->get();
        } else {
            $messages = $contact->messages()->orderByDesc('id')->limit(self::PAGE_SIZE)->get()->reverse()->values();
        }

        return response()->json([
            'messages' => $messages->map(fn ($m) => self::messageRow($m)),
            'contact' => ContactController::row($contact->fresh()),
            'server_time' => now()->toIso8601String(),
            'has_more' => ($after === 0 && ! $since) ? $messages->count() === self::PAGE_SIZE : false,
        ]);
    }

    /** Clear the unread counter and send read receipts (blue ticks) for the last inbound message. */
    public function markRead(Request $request, Contact $contact, MessagingService $messaging): JsonResponse
    {
        Gate::authorize('view', $contact);

        $contact->update(['unread_count' => 0]);

        $last = $contact->messages()->where('direction', Message::DIRECTION_INBOUND)->whereNotNull('wamid')->latest('id')->first();

        if ($last && ! ($last->payload['_read_sent'] ?? false)) {
            try {
                $phone = $last->phoneNumber;
                $messaging->for($phone)->markAsRead($phone->phone_number_id, $last->wamid);
                $last->forceFill(['payload' => ($last->payload ?? []) + ['_read_sent' => true]])->saveQuietly();
            } catch (GraphApiException $e) {
                Log::info('mark-as-read failed', ['contact' => $contact->id, 'error' => $e->displayMessage()]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /** Send from the inbox composer. Returns the stored message as JSON. */
    public function send(InboxSendRequest $request, Contact $contact, SendMessage $send): JsonResponse
    {
        Gate::authorize('view', $contact);

        $data = $request->validated();

        $phone = $data['phone_number_id'] ?? null
            ? PhoneNumber::findOrFail($data['phone_number_id'])
            : ($contact->phoneNumber ?? $request->user()->workspace->phoneNumbers()->where('is_registered', true)->orderByDesc('is_default')->firstOrFail());

        Gate::authorize('update', $phone->account);

        if (! $phone->is_registered) {
            throw ValidationException::withMessages(['phone_number_id' => 'This phone number is not registered for Cloud API.']);
        }

        ConversationGuard::assertCanSend($contact, $data['type']);

        try {
            [$payload, $preview, $template] = OutboundMessageFactory::build($data, $phone, '+'.$contact->wa_id, $data['reply_to'] ?? null);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['type' => $e->getMessage()]);
        }

        try {
            $message = $send($request->user(), $phone, $payload, $preview, $template, $contact);
        } catch (GraphApiException $e) {
            // The failed message row exists; return it so the bubble shows the error.
            $failed = $contact->messages()->latest('id')->first();

            return response()->json([
                'message' => $failed ? self::messageRow($failed) : null,
                'contact' => ContactController::row($contact->fresh()),
                'error' => $e->displayMessage(),
            ], 422);
        }

        return response()->json([
            'message' => self::messageRow($message),
            'contact' => ContactController::row($contact->fresh()),
        ]);
    }

    /* Helpers ------------------------------------------------------------------ */

    protected function conversationList(int $workspaceId, ?string $q): array
    {
        return Contact::where('workspace_id', $workspaceId)
            ->search($q)
            ->orderByDesc('last_message_at')
            ->orderByDesc('unread_count')
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (Contact $c) => ContactController::row($c))
            ->all();
    }

    protected function conversationPayload(Contact $contact): array
    {
        $messages = $contact->messages()->orderByDesc('id')->limit(self::PAGE_SIZE)->get()->reverse()->values();

        return [
            'contact' => ContactController::row($contact->loadMissing('phoneNumber')),
            'messages' => $messages->map(fn ($m) => self::messageRow($m))->all(),
            'has_more' => $messages->count() === self::PAGE_SIZE,
        ];
    }

    public static function messageRow(Message $m): array
    {
        return [
            'id' => $m->id,
            'wamid' => $m->wamid,
            'direction' => $m->direction,
            'type' => $m->type,
            'status' => $m->status,
            'preview' => $m->preview,
            'body' => self::body($m),
            'context_wamid' => $m->context_wamid,
            'template_id' => $m->message_template_id,
            'error_code' => $m->error_code,
            'error_message' => $m->error_message,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'delivered_at' => $m->delivered_at?->toIso8601String(),
            'read_at' => $m->read_at?->toIso8601String(),
            'failed_at' => $m->failed_at?->toIso8601String(),
            'timestamp' => ($m->direction === Message::DIRECTION_INBOUND ? $m->received_at : $m->created_at)?->toIso8601String(),
            'updated_at' => $m->updated_at?->toIso8601String(),
        ];
    }

    /** What to render inside the bubble, by type. */
    protected static function body(Message $m): array
    {
        $p = $m->payload ?? [];

        return match ($m->type) {
            'text' => ['text' => $p['text']['body'] ?? $m->preview],
            'image', 'video', 'document', 'audio', 'sticker' => [
                'media' => [
                    'type' => $m->type,
                    'id' => $p[$m->type]['id'] ?? null,
                    'link' => $p[$m->type]['link'] ?? null,
                    'caption' => $p[$m->type]['caption'] ?? null,
                    'filename' => $p[$m->type]['filename'] ?? null,
                    'mime_type' => $p[$m->type]['mime_type'] ?? null,
                ],
            ],
            'location' => ['location' => $p['location'] ?? null],
            'template' => ['template' => [
                'name' => $p['template']['name'] ?? null,
            ] + TemplateRenderer::render($m->template, $p['template']['components'] ?? [])],
            'interactive' => ['interactive' => $p['interactive'] ?? null, 'text' => $m->preview],
            'button' => ['text' => $p['button']['text'] ?? $m->preview],
            'reaction' => ['text' => ($p['reaction']['emoji'] ?? '').' (reaction)'],
            'unsupported' => ['unsupported' => [
                'kind' => $p['unsupported']['type'] ?? null,
                'code' => $m->error_code,
                'detail' => $m->error_message ?? $m->error_title,
                'hint' => $m->error_code === 131060
                    ? 'First message after this number moved from the WhatsApp Business app — the content is not delivered by Meta. Ask the customer to send again; the 24-hour window is open.'
                    : 'This message type (poll, edited message, GIF, etc.) is not delivered by the Cloud API. Ask the customer to resend as text.',
            ]],
            default => ['text' => $m->preview],
        };
    }
}
