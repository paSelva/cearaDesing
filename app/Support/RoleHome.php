<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Área de cada papel: para onde ir depois do login e que destino pendente faz sentido. */
final class RoleHome
{
    public static function for(?Role $role): string
    {
        return match ($role) {
            Role::Admin => route('filament.admin.pages.dashboard'),
            Role::Jurado => route('filament.jurado.home'),
            Role::Eleitor => route('voter.home'),
            Role::Participante => route('participant.dashboard'),
            null => route('home'),
        };
    }

    /**
     * Destino depois do login: a página que o visitante tentou abrir, desde que não seja a área
     * de outro papel (terminaria em 403 com a senha certa).
     */
    public static function afterLogin(?User $user): string
    {
        $intended = session()->pull('url.intended');
        $home = self::for($user?->role);

        if (! is_string($intended)) {
            return $home;
        }

        $owner = self::ownerOf((string) parse_url($intended, PHP_URL_PATH));

        return $owner === null || $owner === $user?->role ? $intended : $home;
    }

    /** Login certo para a página pedida; admin e jurado não entram pelo login do site (T1). */
    public static function loginFor(Request $request): string
    {
        return match (self::ownerOf($request->path())) {
            Role::Admin => route('filament.admin.auth.login'),
            Role::Jurado => route('filament.jurado.auth.login'),
            default => route('login'),
        };
    }

    private static function ownerOf(string $path): ?Role
    {
        return match (Str::before(ltrim($path, '/'), '/')) {
            'admin', 'organizacao' => Role::Admin,
            'jurado', 'avaliacao' => Role::Jurado,
            'eleitor' => Role::Eleitor,
            'participante' => Role::Participante,
            default => null,
        };
    }
}
