<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Perfil do eleitor (P35). Campos definidos só pela Action RegisterVoter. */
class VoterProfile extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['cpf_hash', 'cpf_encrypted', 'registration_ip'];

    protected function casts(): array
    {
        return ['cpf_encrypted' => 'encrypted'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
