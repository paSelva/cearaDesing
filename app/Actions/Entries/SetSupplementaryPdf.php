<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Models\Entry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** PDF complementar opcional, 1 arquivo de até 10 MB; um novo substitui o anterior (P7, P45). */
final class SetSupplementaryPdf
{
    public function __construct(private readonly EntryGuard $guard) {}

    public function handle(Entry $entry, mixed $file): Media
    {
        $this->guard->ensureEditable($entry);

        Validator::make(['pdf' => $file], [
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('premio.entry.supplementary_pdf.max_kb')],
        ], [
            'pdf.max' => 'O PDF pode ter no máximo 10 MB.',
            'pdf.mimetypes' => 'Envie um arquivo PDF.',
        ], ['pdf' => 'PDF'])->validate();

        /** @var UploadedFile $file */
        return $entry->addMedia($file)
            ->usingName(Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 200, ''))
            ->usingFileName(Str::uuid().'.pdf')
            ->toMediaCollection('supplementary_pdf');
    }
}
