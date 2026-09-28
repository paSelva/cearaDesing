<?php

declare(strict_types=1);

namespace App\Filament\Jurado\Resources\EvaluationResource\Pages;

use App\Filament\Jurado\Resources\EvaluationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEvaluation extends ViewRecord
{
    protected static string $resource = EvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Avaliar')];
    }
}
