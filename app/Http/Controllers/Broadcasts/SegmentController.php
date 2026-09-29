<?php

namespace App\Http\Controllers\Broadcasts;

use App\Http\Controllers\Controller;
use App\Models\Segment;
use App\Support\SegmentQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SegmentController extends Controller
{
    public function index(Request $request): Response
    {
        $segments = $request->user()->workspace->segments()->latest()->get()->map(fn (Segment $s) => self::row($s));

        return Inertia::render('segments/index', ['segments' => $segments, 'total_contacts' => $request->user()->workspace->contacts()->count()]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('segments/form', ['segment' => null, 'schema' => self::schema($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $segment = $request->user()->workspace->segments()->create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('segments.index')->with('success', "Segment {$segment->name} saved.");
    }

    public function edit(Request $request, Segment $segment): Response
    {
        $this->own($request, $segment);

        return Inertia::render('segments/form', ['segment' => self::row($segment) + ['rules' => $segment->rules, 'match' => $segment->match, 'description' => $segment->description], 'schema' => self::schema($request)]);
    }

    public function update(Request $request, Segment $segment): RedirectResponse
    {
        $this->own($request, $segment);
        $segment->update($this->validated($request));

        return redirect()->route('segments.index')->with('success', 'Segment updated.');
    }

    public function destroy(Request $request, Segment $segment): RedirectResponse
    {
        $this->own($request, $segment);
        $segment->delete();

        return redirect()->route('segments.index')->with('success', 'Segment deleted.');
    }

    /** Live count for the rule builder and the broadcast wizard: {match, eligible}. */
    public function count(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rules' => ['nullable', 'array'],
            'match' => ['nullable', Rule::in(['all', 'any'])],
            'segment_id' => ['nullable', 'integer'],
            'require_opt_in' => ['nullable', 'boolean'],
        ]);

        $rules = $data['rules'] ?? null;
        $match = $data['match'] ?? 'all';
        if (! empty($data['segment_id'])) {
            $segment = Segment::where('workspace_id', $request->user()->workspace_id)->findOrFail($data['segment_id']);
            [$rules, $match] = [$segment->rules, $segment->match];
        }

        try {
            [$matching, $eligible] = SegmentQuery::audience($request->user()->workspace_id, $rules, $match, (bool) ($data['require_opt_in'] ?? false));

            return response()->json(['match' => $matching->count(), 'eligible' => $eligible->count()]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'match' => ['required', Rule::in(['all', 'any'])],
            'rules' => ['required', 'array', 'min:1', 'max:20'],
            'rules.*.field' => ['required', 'string', 'max:64'],
            'rules.*.op' => ['required', 'string', 'max:32'],
            'rules.*.value' => ['nullable'],
        ]);
    }

    protected function own(Request $request, Segment $segment): void
    {
        abort_unless($segment->workspace_id === $request->user()->workspace_id, 403);
    }

    public static function row(Segment $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'description' => $s->description,
            'rules_count' => count($s->rules ?? []),
            'count' => $s->contacts()->count(),
            'updated_at' => $s->updated_at?->toIso8601String(),
        ];
    }

    public static function schema(Request $request): array
    {
        $attributeKeys = $request->user()->workspace->contacts()->whereNotNull('custom_fields')->limit(200)->pluck('custom_fields')
            ->flatMap(fn ($a) => array_keys($a ?? []))->unique()->sort()->values();

        return [
            'fields' => SegmentQuery::FIELDS,
            'ops' => SegmentQuery::OPS,
            'attribute_keys' => $attributeKeys,
            'tags' => $request->user()->workspace->contacts()->whereNotNull('tags')->pluck('tags')->flatten()->unique()->sort()->values(),
            'sources' => ['manual', 'inbound', 'outbound', 'import'],
            'opt_in_statuses' => ['unknown', 'opted_in', 'opted_out'],
        ];
    }
}
