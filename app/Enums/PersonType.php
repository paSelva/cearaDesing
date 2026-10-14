<?php

declare(strict_types=1);

namespace App\Enums;

/** Titular da conta de participante (P3). */
enum PersonType: string
{
    case Pf = 'pf';
    case Pj = 'pj';

    public function label(): string
    {
        return match ($this) {
            self::Pf => 'Pessoa física (CPF)',
            self::Pj => 'Pessoa jurídica (CNPJ)',
        };
    }
}
