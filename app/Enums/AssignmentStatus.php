<?php

declare(strict_types=1);

namespace App\Enums;

/** Status da atribuição de jurado a uma inscrição (docs/04 §5). */
enum AssignmentStatus: string
{
    case Pendente = 'pendente';
    case Enviada = 'enviada';
    case Substituida = 'substituida';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Avaliação pendente',
            self::Enviada => 'Avaliação enviada',
            self::Substituida => 'Substituído',
        };
    }
}
