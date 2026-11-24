<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gallery;

use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Edition;
use App\Models\Entry;
use App\Support\PublicResults;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Galeria pública dos projetos aprovados (P24), aberta com a publicação do resultado (P55, P69).
 * Mostra nome, categoria, imagens, distinção e nota final (P57).
 */
final class GalleryController extends Controller
{
    public function __construct(private readonly PublicResults $results) {}

    public function index(): View
    {
        $edition = $this->results->edition();
        $open = $this->results->published($edition);

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

        return view('gallery.index', ['open' => $open, 'categories' => $categories]);
    }

    public function show(Entry $entry): View
    {
        abort_unless($this->isPublic($entry), 404);

        $entry->load(['category', 'media']);

        return view('gallery.show', ['entry' => $entry, 'images' => $entry->getMedia('images')]);
    }

    /** Imagem pública só de inscrição aprovada, com a galeria aberta; cache curto (P78). */
    public function image(Entry $entry, Media $media): StreamedResponse
    {
        abort_unless($this->isPublic($entry), 404);
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
        return $this->results->published($this->results->edition())
            && $entry->status === EntryStatus::Aprovada
            && $entry->edition->year === Edition::YEAR;
    }
}
