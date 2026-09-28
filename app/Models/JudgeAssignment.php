<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssignmentStatus;
use Database\Factories\JudgeAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jurado atribuído a uma inscrição aprovada (P15, P76). Campos definidos só pelas Actions. */
class JudgeAssignment extends Model
{
    /** @use HasFactory<JudgeAssignmentFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'submitted_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_id');
    }

    /** @return HasMany<Score, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
