<?php

declare(strict_types=1);

namespace App\Filament\Resources\PopularVoteResource\Pages;

use App\Actions\Voting\TallyPopularVotes;
use App\Actions\Voting\VotingWindow;
use App\Enums\Role;
use App\Filament\Resources\PopularVoteResource;
use App\Filament\Resources\PopularVoteWinnerResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListPopularVotes extends ListRecords
{
    protected static string $resource = PopularVoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tally')
                ->label('Apurar voto popular')
                ->icon('heroicon-o-calculator')
                ->visible(fn (): bool => auth()->user()?->role === Role::Admin && app(VotingWindow::class)->votingEnded(app(VotingWindow::class)->current()))
                ->requiresConfirmation()
                ->modalDescription('Registra o mais votado de cada categoria (P40). Empates ficam para a decisão da organização (P49). Pode ser refeita.')
                ->action(function (Action $action): void {
                    try {
                        $results = app(TallyPopularVotes::class)->handle(auth()->user());
                    } catch (ValidationException $e) {
                        Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        $action->halt();
                    }

                    Notification::make()
                        ->title('Voto popular apurado')
                        ->body($results->count().' categoria(s); '.$results->where('was_tie', true)->count().' com empate.')
                        ->success()
                        ->send();

                    $this->redirect(PopularVoteWinnerResource::getUrl('index'));
                }),
        ];
    }
}
