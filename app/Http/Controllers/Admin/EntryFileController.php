<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\PrivateMediaResponse;
use App\Models\Entry;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Arquivos das inscrições para a triagem (SPEC-004). Rota separada da área do participante. */
final class EntryFileController extends Controller
{
    public function __invoke(Entry $entry, Media $media): StreamedResponse
    {
        Gate::authorize('view', $entry);

        return PrivateMediaResponse::make($entry, $media);
    }
}
