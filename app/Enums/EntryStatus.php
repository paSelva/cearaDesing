<?php

declare(strict_types=1);

namespace App\Enums;

/** Status da inscrição (docs/04 §4.1). */
enum EntryStatus: string
{
    case Rascunho = 'rascunho';
    case Enviada = 'enviada';
    case Aprovada = 'aprovada';
    case Desclassificada = 'desclassificada';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho (não enviada)',
            self::Enviada => 'Enviada',
            self::Aprovada => 'Aprovada na triagem',
            self::Desclassificada => 'Desclassificada',
        };
    }

    /** O participante só edita rascunho e enviada (P8, P70). */
    public function isEditableByParticipant(): bool
    {
        return $this === self::Rascunho || $this === self::Enviada;
    }
}
