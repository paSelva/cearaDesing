<?php

declare(strict_types=1);

namespace App\Filament\Resources\EditionResource\Pages;

use App\Actions\Admin\UpdateEditionSettings;
use App\Actions\Results\PublishResults;
use App\Enums\Role;
use App\Filament\Resources\EditionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditEdition extends EditRecord
{
    protected static string $resource = EditionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'name',
            'registration_starts_at',
            'registration_ends_at',
            'judging_starts_at',
            'judging_ends_at',
            'voting_starts_at',
            'voting_ends_at',
            'results_announce_on',
            'ceremony_on',
            'project_period_starts_on',
            'project_period_ends_on',
            'judges_per_entry',
            'band_gold_min',
            'band_silver_min',
            'band_bronze_min',
            'band_honorable_min',
        ]));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateEditionSettings::class)->handle($record, $data);
    }

    /** SPEC-011: publicação do resultado pelo admin (P69). */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publishResults')
                ->label('Publicar resultado')
                ->icon('heroicon-o-megaphone')
                ->color('success')
                ->visible(fn (): bool => auth()->user()?->role === Role::Admin && $this->getRecord()->results_published_at === null)
                ->requiresConfirmation()
                ->modalHeading('Publicar o resultado?')
                ->modalDescription(function (): string {
                    $pending = app(PublishResults::class)->pendingItems($this->getRecord());

                    return $pending === []
                        ? 'A página de premiados fica pública, a galeria passa a mostrar distinção e nota, e cada participante vê a nota final na área dele. Não há como despublicar pelo sistema.'
                        : 'Ainda não é possível publicar: '.implode(' ', $pending);
                })
                ->action(function (Action $action): void {
                    try {
                        app(PublishResults::class)->handle(auth()->user());
                    } catch (ValidationException $e) {
                        Notification::make()->title('Não publicado')->body(collect($e->errors())->flatten()->implode(' '))->danger()->persistent()->send();
                        $action->halt();
                    }

                    Notification::make()->title('Resultado publicado.')->success()->send();
                    $this->getRecord()->refresh();
                }),
        ];
    }
}
