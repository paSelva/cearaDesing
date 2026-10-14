<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nota de 1 a 5 de um critério (§5). Gravada só pelas Actions. */
class Score extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    /** @return BelongsTo<JudgeAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(JudgeAssignment::class, 'judge_assignment_id');
    }

    /** @return BelongsTo<Criterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    /** @return HasMany<ScoreRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ScoreRevision::class);
    }
}
