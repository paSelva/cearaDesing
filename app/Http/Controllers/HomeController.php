<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Entries\EntryGuard;
use App\Models\Category;
use App\Models\Edition;
use App\Support\PublicResults;
use Illuminate\View\View;

/**
 * Site público do prêmio (P26). Conteúdo do edital; datas, categorias, critérios e faixas
 * vêm do banco (editáveis no admin, §7). Parceiros, diretoria e avaliadores aparecem
 * quando houver conteúdo cadastrado (⚠️ P27, P59).
 */
final class HomeController extends Controller
{
    public function __invoke(EntryGuard $guard, PublicResults $results): View
    {
        $edition = Edition::query()->where('year', Edition::YEAR)->first();

        $categories = $edition === null
            ? collect()
            : Category::query()->where('edition_id', $edition->id)->orderBy('sort')->with('criteria')->get();

        return view('welcome', [
            'edition' => $edition,
            'registrationOpen' => $edition !== null && $guard->isRegistrationOpen($edition),
            'resultsPublished' => $results->published($edition),
            'categories' => $categories,
            'defaultCriteria' => $categories->first(fn (Category $c) => $c->code === '3.1')?->criteria ?? collect(),
            'exportCategory' => $categories->first(fn (Category $c) => $c->code === '3.9'),
            // Prontos para receber conteúdo; hoje não há cadastro (⚠️ P27, P59).
            'partners' => collect(),
            'boardMembers' => collect(),
            'judges' => collect(),
        ]);
    }
}
