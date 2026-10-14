<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jurado;

use App\Http\Controllers\Controller;
use App\Http\Responses\PrivateMediaResponse;
use App\Models\JudgeAssignment;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Imagens e PDF da inscrição para o jurado atribuído (P13). Não inclui comprovações. */
final class AssignmentFileController extends Controller
{
    public function __invoke(JudgeAssignment $assignment, Media $media): StreamedResponse
    {
        Gate::authorize('view', $assignment);
        abort_unless(in_array($media->collection_name, ['images', 'supplementary_pdf'], true), 404);

        return PrivateMediaResponse::make($assignment->entry, $media);
    }
}
