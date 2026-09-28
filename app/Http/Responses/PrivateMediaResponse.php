<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Entrega um arquivo do disco privado; 404 se o arquivo não pertence ao registro informado. */
final class PrivateMediaResponse
{
    public static function make(Model $owner, Media $media): StreamedResponse
    {
        abort_unless(
            $media->model_type === $owner->getMorphClass() && (int) $media->model_id === (int) $owner->getKey(),
            404,
        );

        return response()->stream(function () use ($media): void {
            $stream = $media->stream();
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addcslashes($media->file_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
