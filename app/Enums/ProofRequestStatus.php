<?php

declare(strict_types=1);

namespace App\Enums;

enum ProofRequestStatus: string
{
    case Pendente = 'pendente';
    case Respondida = 'respondida';
    case Aceita = 'aceita';
    case Recusada = 'recusada';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Aguardando envio',
            self::Respondida => 'Enviada, em análise',
            self::Aceita => 'Aceita pela organização',
            self::Recusada => 'Recusada pela organização',
        };
    }
}
