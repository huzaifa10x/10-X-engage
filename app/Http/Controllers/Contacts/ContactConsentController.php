<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Manual opt-in / opt-out from the contact page (recorded in the consent ledger with the acting user). */
class ContactConsentController extends Controller
{
    public function update(Request $request, Contact $contact, ConsentService $consent): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $data = $request->validate([
            'status' => ['required', Rule::in(['opted_in', 'opted_out', 'unknown'])],
            'scope' => ['nullable', 'array'],
            'scope.*' => [Rule::in(ConsentService::SCOPES)],
            'consent_text' => ['nullable', 'string', 'max:1000'],
        ]);

        match ($data['status']) {
            'opted_in' => $consent->optIn($contact, 'ui', $data['scope'] ?: ConsentService::SCOPES, null, $data['consent_text'] ?? null, $request->user()->id),
            'opted_out' => $consent->optOut($contact, 'ui', null, $request->user()->id, 'manual'),
            default => $consent->clear($contact, $request->user()->id),
        };

        return back()->with('success', 'Consent updated.');
    }
}
