<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Criterion;
use App\Models\Edition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $edition = Edition::query()->where('year', Edition::YEAR)->first();

        return [
            Stat::make('Edição configurada', $edition?->name ?? 'Não semeada'),
            Stat::make('Categorias do catálogo', $edition?->categories()->count() ?? 0),
            Stat::make('Critérios do catálogo', $edition
                ? Criterion::query()->whereHas('category', fn ($query) => $query->where('edition_id', $edition->id))->count()
                : 0),
        ];
    }
}
