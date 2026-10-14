<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Voting\VotingWindow;
use App\Enums\Distinction;
use App\Enums\EntryStatus;
use App\Models\Category;
use App\Models\PopularVoteWinner;
use Illuminate\View\View;

/**
 * Página pública de premiados (P21), visível depois do botão "Publicar resultado" (P69).
 * Mostra nome, categoria, distinção, nota final, autoria e créditos, imagens e textos (P44)
 * e o mais votado do voto popular de cada categoria (P51). Nunca as notas por jurado (P22).
 */
final class ResultsController extends Controller
{
    public function __invoke(VotingWindow $window): View
    {
        $edition = $window->current();
        $published = $window->resultsPublished($edition);

        $awarded = [Distinction::Ouro, Distinction::Prata, Distinction::Bronze, Distinction::MencaoHonrosa];

        $categories = ! $published ? collect() : Category::query()
            ->where('edition_id', $edition->id)
            ->orderBy('sort')
            ->with(['entries' => fn ($query) => $query
                ->where('status', EntryStatus::Aprovada->value)
                ->whereIn('distinction', array_map(fn (Distinction $d) => $d->value, $awarded))
                ->orderByDesc('final_score')
                ->orderBy('title')
                ->with('media')])
            ->get();

        $popularWinners = ! $published ? collect() : PopularVoteWinner::query()
            ->whereIn('category_id', $categories->pluck('id'))
            ->whereNotNull('entry_id')
            // Desclassificada depois da apuração não aparece (P78).
            ->whereHas('entry', fn ($query) => $query->where('status', EntryStatus::Aprovada->value))
            ->with('entry')
            ->get()
            ->keyBy('category_id');

        return view('results.index', [
            'edition' => $edition,
            'published' => $published,
            'categories' => $categories,
            'popularWinners' => $popularWinners,
            'awarded' => $awarded,
        ]);
    }
}
