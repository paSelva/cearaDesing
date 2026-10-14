<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CriterionResource\Pages\ListCriteria;
use App\Models\Criterion;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CriterionResource extends Resource
{
    protected static ?string $model = Criterion::class;

    protected static ?string $navigationLabel = 'Critérios';

    protected static ?string $pluralModelLabel = 'Critérios';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.code')->label('Categoria')->sortable(),
                TextColumn::make('sort')->label('Ordem')->sortable(),
                TextColumn::make('name')->label('Critério')->searchable(),
                TextColumn::make('description')->label('Descrição')->wrap(),
            ])
            ->defaultSort('category_id')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('category.edition', fn (Builder $query): Builder => $query->where('year', 2026));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCriteria::route('/'),
        ];
    }
}
