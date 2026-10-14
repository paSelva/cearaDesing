<?php

declare(strict_types=1);

namespace App\Filament\Resources\EditionResource\Pages;

use App\Filament\Resources\EditionResource;
use Filament\Resources\Pages\ListRecords;

class ListEditions extends ListRecords
{
    protected static string $resource = EditionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
