<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Edition;

/** Distinções do §6. As faixas vêm de `editions.band_*`. */
enum Distinction: string
{
    case Ouro = 'ouro';
    case Prata = 'prata';
    case Bronze = 'bronze';
    case MencaoHonrosa = 'mencao_honrosa';
    case SemDistincao = 'sem_distincao';

    public static function fromScore(int $score, Edition $edition): self
    {
        return match (true) {
            $score >= $edition->band_gold_min => self::Ouro,
            $score >= $edition->band_silver_min => self::Prata,
            $score >= $edition->band_bronze_min => self::Bronze,
            $score >= $edition->band_honorable_min => self::MencaoHonrosa,
            default => self::SemDistincao,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ouro => 'Ouro',
            self::Prata => 'Prata',
            self::Bronze => 'Bronze',
            self::MencaoHonrosa => 'Menção Honrosa',
            self::SemDistincao => 'Sem distinção',
        };
    }
}
