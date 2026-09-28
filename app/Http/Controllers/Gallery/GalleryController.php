<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gallery;

use App\Actions\Voting\CastPopularVote;
use App\Actions\Voting\VotingWindow;
use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Edition;
use App\Models\Entry;
use App\Models\PopularVote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Galeria pública dos projetos aprovados (P24), onde acontece o voto popular (P55).
 * Mostra só nome, categoria e imagens (P55); distinção e nota só depois da publicação (P57, P69).
 * A contagem de votos nunca aparece ao público (P39).
 */
final class GalleryController extends Controller
{
    public function __construct(private readonly VotingWindow $window) {}

    public function index(Request $request): View
    {
        $edition = $this->window->current();
        $open = $this->window->galleryOpen($edition);

        $categories = ! $open ? collect() : Category::query()
            ->where('edition_id', $edition->id)
            ->orderBy('sort')
            ->with(['entries' => fn ($query) => $query
                ->where('status', EntryStatus::Aprovada->value)
                ->select(['id', 'category_id', 'title', 'status', 'final_score', 'distinction'])
                ->orderBy('title')
                ->with('media')])
            ->get()
            ->filter(fn (Category $category) => $category->entries->isNotEmpty());

        return view('gallery.index', [
            'edition' => $edition,
            'open' => $open,
            'votingOpen' => $this->window->votingOpen($edition),
            'showResults' => $this->window->resultsPublished($edition),
            'categories' => $categories,
            'myVotes' => $this->myVotes($request),
        ]);
    }

    public function show(Request $request, Entry $entry): View
    {
        $edition = $this->window->current();
        abort_unless($this->window->galleryOpen($edition) && $this->isPublic($entry), 404);

        $entry->load(['category', 'media']);

        return view('gallery.show', [
            'entry' => $entry,
            'images' => $entry->getMedia('images'),
            'votingOpen' => $this->window->votingOpen($edition),
            'showResults' => $this->window->resultsPublished($edition),
            'myVotes' => $this->myVotes($request),
        ]);
    }

    public function vote(Request $request, Entry $entry, CastPopularVote $action): RedirectResponse
    {
        $action->handle($request->user(), $entry, (string) $request->ip());

        return redirect()->route('gallery.show', $entry)->with('status', 'Voto registrado. Obrigado por participar!');
    }

    /** Imagem pública só de inscrição aprovada, com a galeria aberta; cache curto (P78). */
    public function image(Entry $entry, Media $media): StreamedResponse
    {
        abort_unless($this->window->galleryOpen($this->window->current()) && $this->isPublic($entry), 404);
        abort_unless(
            $media->collection_name === 'images'
                && $media->model_type === $entry->getMorphClass()
                && (int) $media->model_id === $entry->id,
            404,
        );

        return response()->stream(function () use ($media): void {
            $stream = $media->stream();
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function isPublic(Entry $entry): bool
    {
        return $entry->status === EntryStatus::Aprovada && $entry->edition->year === Edition::YEAR;
    }

    /** @return Collection<int, int> category_id => entry_id votados pelo eleitor logado */
    private function myVotes(Request $request): Collection
    {
        $user = $request->user();
        if ($user?->role !== Role::Eleitor) {
            return collect();
        }

        return PopularVote::query()->where('voter_id', $user->id)->pluck('entry_id', 'category_id');
    }
}
