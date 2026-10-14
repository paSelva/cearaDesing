<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EntryStatus;
use App\Filament\Resources\PopularVoteResource\Pages\ListPopularVotes;
use App\Models\Edition;
use App\Models\Entry;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Parcial do voto popular para a organização (P48): votos por projeto aprovado. Votos em
 * desclassificadas ficam fora da contagem (P78). O público nunca vê a contagem (P39).
 */
class PopularVoteResource extends Resource
{
    protected static ?string $model = Entry::class;

    protected static ?string $slug = 'voto-popular';

    protected static ?string $navigationLabel = 'Parcial';

    protected static ?string $modelLabel = 'projeto';

    protected static ?string $pluralModelLabel = 'voto popular — parcial';

    protected static string|\UnitEnum|null $navigationGroup = 'Voto popular';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.code')->label('Cat.')->sortable(),
                TextColumn::make('category.name')->label('Categoria'),
                TextColumn::make('title')->label('Projeto')->searchable()->limit(60),
                TextColumn::make('votes_count')->label('Votos')->sortable(),
            ])
            ->defaultSort('votes_count', 'desc')
            ->filters([
                SelectFilter::make('category')->label('Categoria')->relationship('category', 'name'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', EntryStatus::Aprovada->value)
            ->whereHas('edition', fn (Builder $query) => $query->where('year', Edition::YEAR))
            ->with('category')
            ->withCount('popularVotes as votes_count');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListPopularVotes::route('/')];
    }
}
