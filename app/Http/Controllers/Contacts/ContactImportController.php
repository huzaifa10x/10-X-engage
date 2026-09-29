<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CSV import: upload → preview (auto-mapped columns) → import. Rows are matched on WhatsApp number;
 * existing contacts are updated (never duplicated), tags are merged.
 */
class ContactImportController extends Controller
{
    public const FIELDS = ['name', 'phone', 'email', 'company', 'tags', 'opt_in', 'notes'];

    public function create(): Response
    {
        return Inertia::render('contacts/import', ['fields' => self::FIELDS]);
    }

    /** Parse the file and suggest a column mapping. Returns headers + first rows as JSON. */
    public function preview(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        [$headers, $rows] = self::parse($request->file('file')->getRealPath(), 6);

        return response()->json([
            'headers' => $headers,
            'rows' => $rows,
            'mapping' => self::guessMapping($headers),
            'total' => max(0, self::countLines($request->file('file')->getRealPath()) - 1),
        ]);
    }

    public function store(Request $request, ConsentService $consent): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', Rule::in(self::FIELDS)],
            'default_tags' => ['nullable', 'string', 'max:200'],
            'mark_opted_in' => ['nullable', 'boolean'],
            'consent_text' => ['nullable', 'string', 'max:1000'],
        ]);

        $mapping = array_filter($data['mapping']);           // header index => field
        if (! in_array('phone', $mapping, true)) {
            return back()->withErrors(['mapping' => 'Map one column to the WhatsApp number.']);
        }

        $workspaceId = $request->user()->workspace_id;
        $defaultTags = collect(explode(',', (string) ($data['default_tags'] ?? '')))->map(fn ($t) => trim($t))->filter()->values()->all();
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        [, $rows] = self::parse($request->file('file')->getRealPath(), null);

        foreach ($rows as $i => $row) {
            $record = [];
            foreach ($mapping as $index => $field) {
                $record[$field] = trim((string) ($row[$index] ?? ''));
            }

            $waId = Contact::normalizeWaId($record['phone'] ?? '');
            if (strlen($waId) < 7 || strlen($waId) > 15) {
                $stats['skipped']++;
                if (count($stats['errors']) < 20) {
                    $stats['errors'][] = 'Row '.($i + 2).": invalid number '{$record['phone']}'";
                }

                continue;
            }

            $contact = Contact::firstOrNew(['workspace_id' => $workspaceId, 'wa_id' => $waId]);
            $isNew = ! $contact->exists;

            $tags = collect($contact->tags ?? [])
                ->merge(explode(',', (string) ($record['tags'] ?? '')))
                ->merge($defaultTags)
                ->map(fn ($t) => trim((string) $t))->filter()->unique()->values()->all();

            $contact->fill(array_filter([
                'name' => $record['name'] ?: $contact->name,
                'email' => $record['email'] ?: $contact->email,
                'company' => $record['company'] ?: $contact->company,
                'notes' => $record['notes'] ?: $contact->notes,
            ]) + ['phone' => '+'.$waId, 'tags' => $tags]);

            if ($isNew) {
                $contact->source = 'import';
                $contact->created_by = $request->user()->id;
            }
            $contact->save();

            $optIn = strtolower((string) ($record['opt_in'] ?? ''));
            if (($data['mark_opted_in'] ?? false) || in_array($optIn, ['1', 'yes', 'true', 'y', 'opted_in'], true)) {
                if ($contact->opt_in_status !== Contact::OPT_IN) {
                    $consent->optIn($contact, 'import', ConsentService::SCOPES, null, $data['consent_text'] ?? null, $request->user()->id);
                }
            } elseif (in_array($optIn, ['0', 'no', 'false', 'n', 'opted_out'], true) && $contact->opt_in_status !== Contact::OPT_OUT) {
                $consent->optOut($contact, 'import', null, $request->user()->id);
            }

            $isNew ? $stats['created']++ : $stats['updated']++;
        }

        return redirect()->route('contacts.index')->with('success', "Import finished: {$stats['created']} created, {$stats['updated']} updated, {$stats['skipped']} skipped.")
            ->with('warning', $stats['errors'] ? implode(' | ', $stats['errors']) : null);
    }

    /** @return array{0: list<string>, 1: list<list<string>>} */
    public static function parse(string $path, ?int $limit): array
    {
        $handle = fopen($path, 'r');
        $headers = [];
        $rows = [];
        $first = true;
        while (($line = fgetcsv($handle)) !== false) {
            if ($first) {
                $headers = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $line);
                $first = false;

                continue;
            }
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = $line;
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }
        fclose($handle);

        return [$headers, $rows];
    }

    public static function guessMapping(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $h));
            $map[$i] = match (true) {
                in_array($key, ['phone', 'whatsapp', 'whatsappnumber', 'number', 'mobile', 'msisdn', 'waid', 'phonenumber', 'tel'], true) => 'phone',
                in_array($key, ['name', 'fullname', 'contact', 'contactname', 'customer'], true) => 'name',
                in_array($key, ['email', 'emailaddress', 'mail'], true) => 'email',
                in_array($key, ['company', 'organisation', 'organization', 'business'], true) => 'company',
                in_array($key, ['tags', 'tag', 'labels', 'segment'], true) => 'tags',
                in_array($key, ['optin', 'consent', 'subscribed', 'optinstatus'], true) => 'opt_in',
                in_array($key, ['notes', 'note', 'comments'], true) => 'notes',
                default => null,
            };
        }

        return $map;
    }

    protected static function countLines(string $path): int
    {
        $n = 0;
        $h = fopen($path, 'r');
        while (fgets($h) !== false) {
            $n++;
        }
        fclose($h);

        return $n;
    }
}
