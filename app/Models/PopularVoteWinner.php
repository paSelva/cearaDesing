<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mais votado da categoria (P40) e decisão da organização em empate (P49). */
class PopularVoteWinner extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'was_tie' => 'boolean',
            'tied_entry_ids' => 'array',
            'votes' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPendingDecision(): bool
    {
        return $this->was_tie && $this->entry_id === null;
    }
}
