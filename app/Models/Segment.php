<?php

namespace App\Models;

use App\Support\SegmentQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved rule, not a stored list — membership is evaluated live against the contacts table. */
class Segment extends Model
{
    protected $fillable = ['workspace_id', 'name', 'description', 'match', 'rules', 'created_by'];

    protected function casts(): array
    {
        return ['rules' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contacts(): Builder
    {
        return SegmentQuery::apply(Contact::where('workspace_id', $this->workspace_id), $this->rules ?? [], $this->match);
    }
}
