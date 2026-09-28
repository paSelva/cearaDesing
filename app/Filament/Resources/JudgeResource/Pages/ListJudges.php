<?php

declare(strict_types=1);

namespace App\Filament\Resources\JudgeResource\Pages;

use App\Filament\Resources\JudgeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJudges extends ListRecords
{
    protected static string $resource = JudgeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Novo jurado')];
    }
}
