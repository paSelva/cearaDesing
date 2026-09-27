<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Jurado = 'jurado';
    case Participante = 'participante';
    case Eleitor = 'eleitor';
}
