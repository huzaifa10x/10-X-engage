<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        $query = $workspace->contacts()->with('phoneNumber:id,display_phone_number')->search($request->query('q'));

        match ($request->query('window')) {
            'open' => $query->where('window_expires_at', '>', now()),
            'closed' => $query->where(fn ($q) => $q->whereNull('window_expires_at')->orWhere('window_expires_at', '<=', now())),
            default => null,
        };
        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $contacts = $query->orderByDesc('last_message_at')->orderBy('name')->paginate(25)->withQueryString()
            ->through(fn (Contact $c) => self::row($c));

        $tags = $workspace->contacts()->whereNotNull('tags')->pluck('tags')->flatten()->unique()->sort()->values();

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'filters' => $request->only('q', 'window', 'tag'),
            'tags' => $tags,
            'stats' => [
                'total' => $workspace->contacts()->count(),
                'open_windows' => $workspace->contacts()->where('window_expires_at', '>', now())->count(),
                'unread' => (int) $workspace->contacts()->sum('unread_count'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('contacts/form', [
            'contact' => null,
            'phones' => $this->phones($request),
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = $request->user()->workspace->contacts()->create($request->validated() + [
            'source' => 'manual',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('inbox.show', $contact)->with('success', "Contact {$contact->name} created. Start the conversation with a template.");
    }

    public function edit(Request $request, Contact $contact): Response
    {
        Gate::authorize('update', $contact);

        return Inertia::render('contacts/form', [
            'contact' => self::row($contact) + ['email' => $contact->email, 'company' => $contact->company, 'notes' => $contact->notes, 'phone_number_id' => $contact->phone_number_id],
            'phones' => $this->phones($request),
        ]);
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $contact->update($request->validated());

        return redirect()->route('contacts.index')->with('success', 'Contact updated.');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $contact->delete(); // messages keep their rows (contact_id -> null)

        return redirect()->route('contacts.index')->with('success', 'Contact deleted.');
    }

    protected function phones(Request $request): array
    {
        return $request->user()->workspace->phoneNumbers()->where('is_registered', true)
            ->get(['phone_numbers.id', 'display_phone_number', 'verified_name'])
            ->map(fn ($p) => ['id' => $p->id, 'label' => trim($p->display_phone_number.' · '.$p->verified_name, ' ·')])
            ->all();
    }

    public static function row(Contact $c): array
    {
        return [
            'id' => $c->id,
            'wa_id' => $c->wa_id,
            'phone' => $c->phone ?? '+'.$c->wa_id,
            'name' => $c->name,
            'display_name' => $c->displayName(),
            'initials' => $c->initials(),
            'email' => $c->email,
            'company' => $c->company,
            'tags' => $c->tags ?? [],
            'source' => $c->source,
            'sender' => $c->relationLoaded('phoneNumber') && $c->phoneNumber ? $c->phoneNumber->display_phone_number : null,
            'unread_count' => $c->unread_count,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'last_message_preview' => $c->last_message_preview,
            'last_message_direction' => $c->last_message_direction,
            'window' => $c->windowState(),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
