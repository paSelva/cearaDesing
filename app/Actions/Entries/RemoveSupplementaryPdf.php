<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Models\Entry;

final class RemoveSupplementaryPdf
{
    public function __construct(private readonly EntryGuard $guard) {}

    public function handle(Entry $entry): void
    {
        $this->guard->ensureEditable($entry);

        $entry->clearMediaCollection('supplementary_pdf');
    }
}
