<?php

declare(strict_types=1);

namespace App\Filament\Jurado\Resources\EvaluationResource\Pages;

use App\Filament\Jurado\Resources\EvaluationResource;
use Filament\Resources\Pages\ListRecords;

class ListEvaluations extends ListRecords
{
    protected static string $resource = EvaluationResource::class;
}
