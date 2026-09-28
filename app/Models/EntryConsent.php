<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aceite do §8 com data/hora e IP (P11, P53). Só é criado, nunca alterado. */
#[Fillable(['consent', 'accepted_at', 'ip'])]
class EntryConsent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'consent' => ConsentType::class,
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
