<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Voting\DecidePopularVoteTie;
use App\Filament\Resources\PopularVoteWinnerResource\Pages\ListPopularVoteWinners;
use App\Models\Entry;
use App\Models\PopularVoteWinner;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/** Resultado da apuração do voto popular por categoria (P40) e decisão de empate (P49). */
class PopularVoteWinnerResource extends Resource
{
    protected static ?string $model = PopularVoteWinner::class;

    protected static ?string $slug = 'voto-popular-resultado';

    protected static ?string $navigationLabel = 'Resultado da apuração';

    protected static ?string $modelLabel = 'resultado';

    protected static ?string $pluralModelLabel = 'resultado do voto popular';

    protected static string|\UnitEnum|null $navigationGroup = 'Voto popular';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.code')->label('Cat.'),
                TextColumn::make('category.name')->label('Categoria'),
                TextColumn::make('entry.title')->label('Mais votado')->placeholder('Empate: aguardando decisão'),
                TextColumn::make('votes')->label('Votos'),
                IconColumn::make('was_tie')->label('Empate')->boolean(),
                TextColumn::make('decision_note')->label('Observação da decisão')->placeholder('—')->wrap(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['category', 'entry']))
            ->recordActions([
                Action::make('decideTie')
                    ->label('Decidir empate')
                    ->icon('heroicon-o-scale')
                    ->visible(fn (PopularVoteWinner $record): bool => $record->was_tie)
                    ->schema(fn (PopularVoteWinner $record): array => [
                        Select::make('entry_id')
                            ->label('Projeto escolhido pela organização')
                            ->options(Entry::query()->whereIn('id', $record->tied_entry_ids ?? [])->pluck('title', 'id')->all())
                            ->required(),
                        Textarea::make('decision_note')->label('Observação')->maxLength(5000)->rows(3),
                    ])
                    ->action(function (PopularVoteWinner $record, array $data, Action $action): void {
                        try {
                            app(DecidePopularVoteTie::class)->handle($record, auth()->user(), $data);
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                            $action->halt();
                        }

                        Notification::make()->title('Empate decidido.')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListPopularVoteWinners::route('/')];
    }
}
