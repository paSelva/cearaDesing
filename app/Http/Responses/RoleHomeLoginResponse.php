<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\Role;
use Laravel\Fortify\Contracts\LoginResponse;

/** Depois do login no site, cada papel vai para a própria área (SPEC-009). */
final class RoleHomeLoginResponse implements LoginResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return redirect()->intended(self::homeFor($request->user()?->role));
    }

    public static function homeFor(?Role $role): string
    {
        return match ($role) {
            Role::Eleitor => route('voter.home'),
            default => route('participant.dashboard'),
        };
    }
}
