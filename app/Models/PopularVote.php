<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Voto popular: final, 1 por categoria por eleitor (P36, P71). Criado só pela Action. */
class PopularVote extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected $hidden = ['ip'];

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }
}
