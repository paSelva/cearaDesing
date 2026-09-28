<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Histórico de correção de nota pela organização (P19, P46). Nunca alterado. */
class ScoreRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    /** @return BelongsTo<Score, $this> */
    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
