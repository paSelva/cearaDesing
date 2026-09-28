<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntryResource\Pages;

use App\Filament\Resources\EntryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEntry extends ViewRecord
{
    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EntryResource::approveAction(),
            EntryResource::disqualifyAction(),
        ];
    }
}
