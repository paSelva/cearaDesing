<?php

declare(strict_types=1);

namespace App\Enums;

/** Tipos de participante listados no §2 do edital (P4). */
enum ParticipantType: string
{
    case Profissional = 'profissional';
    case EscritorioEstudioEquipeColetivo = 'escritorio_estudio_equipe_coletivo';
    case Estudante = 'estudante';
    case Empresa = 'empresa';
    case Instituicao = 'instituicao';
    case Osc = 'osc';

    public function label(): string
    {
        return match ($this) {
            self::Profissional => 'Profissional de design',
            self::EscritorioEstudioEquipeColetivo => 'Escritório, estúdio, equipe ou coletivo',
            self::Estudante => 'Estudante',
            self::Empresa => 'Empresa',
            self::Instituicao => 'Instituição pública ou privada',
            self::Osc => 'Organização da sociedade civil',
        };
    }
}
