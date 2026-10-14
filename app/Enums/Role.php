<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Jurado = 'jurado';
    case Participante = 'participante';
    case Eleitor = 'eleitor';

    /**
     * Papéis que usam a autenticação do site (Fortify): login e recuperação de senha.
     * Admin e jurado usam o Filament (T1).
     *
     * @return list<self>
     */
    public static function siteAuth(): array
    {
        return [self::Participante, self::Eleitor];
    }

    public function usesSiteAuth(): bool
    {
        return in_array($this, self::siteAuth(), true);
    }
}
