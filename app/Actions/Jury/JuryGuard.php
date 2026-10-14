<?php

declare(strict_types=1);

namespace App\Actions\Jury;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Verificações comuns às Actions de júri da organização. */
final class JuryGuard
{
    public function ensureAdmin(User $user): void
    {
        if ($user->role !== Role::Admin) {
            throw ValidationException::withMessages(['judges' => 'Somente a organização gerencia o júri.']);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function ensureJudges(array $ids, string $field = 'judges'): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $judges = User::query()->whereIn('id', $ids)->where('role', Role::Jurado->value)->count();

        if ($judges !== count($ids)) {
            throw ValidationException::withMessages([$field => 'Selecione apenas usuários com papel de jurado.']);
        }

        return $ids;
    }
}
