<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Distinction;
use App\Enums\EntryStatus;
use App\Models\Category;
use App\Support\PublicResults;
use Illuminate\View\View;

/**
 * Página pública de premiados (P21), visível depois do botão "Publicar resultado" (P69).
 * Mostra nome, categoria, distinção, nota final, autoria e créditos, imagens e textos
 * (P44). Nunca as notas por jurado (P22).
 */
final class ResultsController extends Controller
{
    public function __invoke(PublicResults $results): View
    {
        $edition = $results->edition();
        $published = $results->published($edition);

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

        return view('results.index', [
            'edition' => $edition,
            'published' => $published,
            'categories' => $categories,
            'awarded' => $awarded,
        ]);
    }
}
