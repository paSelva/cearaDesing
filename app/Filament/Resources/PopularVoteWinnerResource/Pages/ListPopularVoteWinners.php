<?php

declare(strict_types=1);

namespace App\Filament\Resources\PopularVoteWinnerResource\Pages;

use App\Filament\Resources\PopularVoteWinnerResource;
use Filament\Resources\Pages\ListRecords;

class ListPopularVoteWinners extends ListRecords
{
    protected static string $resource = PopularVoteWinnerResource::class;
}
