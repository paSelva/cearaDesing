<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Models\Edition;

/** Janelas do voto popular, lidas de `editions` (§7, P38, P55, P57). Fuso America/Fortaleza. */
final class VotingWindow
{
    /** A galeria abre no início da votação (P55) e continua no ar depois (P57). */
    public function galleryOpen(?Edition $edition): bool
    {
        return $edition !== null && now()->greaterThanOrEqualTo($edition->voting_starts_at);
    }

    public function votingOpen(?Edition $edition): bool
    {
        return $edition !== null
            && now()->greaterThanOrEqualTo($edition->voting_starts_at)
            && now()->lessThanOrEqualTo($edition->voting_ends_at);
    }

    public function votingEnded(?Edition $edition): bool
    {
        return $edition !== null && now()->greaterThan($edition->voting_ends_at);
    }

    /** Distinção e nota na galeria só depois da publicação do resultado pelo admin (P57, P69). */
    public function resultsPublished(?Edition $edition): bool
    {
        return $edition?->results_published_at !== null;
    }

    public function current(): ?Edition
    {
        return Edition::query()->where('year', Edition::YEAR)->first();
    }
}
