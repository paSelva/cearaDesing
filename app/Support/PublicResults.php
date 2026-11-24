<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Edition;

/** Galeria e premiados ficam públicos juntos, quando o admin publica o resultado (P55, P69). */
final class PublicResults
{
    public function edition(): ?Edition
    {
        return Edition::query()->where('year', Edition::YEAR)->first();
    }

    public function published(?Edition $edition): bool
    {
        return $edition?->results_published_at !== null;
    }
}
