<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntryResource\RelationManagers;

use App\Actions\Proofs\RequestProof;
use App\Actions\Proofs\ReviewProof;
use App\Enums\EntryStatus;
use App\Enums\ProofRequestStatus;
use App\Models\Entry;
use App\Models\ProofRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/** Comprovações da inscrição (P12, P54, P56, P79 — SPEC-005). */
class ProofRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'proofRequests';

    protected static ?string $title = 'Comprovações';

    protected static ?string $modelLabel = 'comprovação';

    protected static ?string $pluralModelLabel = 'comprovações';

    public function isReadOnly(): bool
    {
        // A página é de visualização, mas pedir e analisar comprovação é ação da organização.
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Pedida em')->dateTime('d/m/Y H:i', 'America/Fortaleza'),
                TextColumn::make('message')->label('O que foi pedido')->wrap()->limit(120),
                TextColumn::make('deadline_at')->label('Prazo')->dateTime('d/m/Y H:i', 'America/Fortaleza'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (ProofRequestStatus $state): string => $state->label())
                    ->color(fn (ProofRequestStatus $state): string => match ($state) {
                        ProofRequestStatus::Pendente => 'gray',
                        ProofRequestStatus::Respondida => 'warning',
                        ProofRequestStatus::Aceita => 'success',
                        ProofRequestStatus::Recusada => 'danger',
                    }),
                TextColumn::make('responded_at')->label('Enviada em')->dateTime('d/m/Y H:i', 'America/Fortaleza')->placeholder('—'),
                IconColumn::make('is_late')->label('Atrasada')->boolean(),
            ])
            ->headerActions([
                Action::make('requestProof')
                    ->label('Pedir comprovação')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->visible(fn (): bool => in_array($this->getOwnerRecord()->status, [EntryStatus::Enviada, EntryStatus::Aprovada], true))
                    ->modalDescription('O participante vê o pedido na área dele e envia 1 PDF de até 10 MB. O status da inscrição não muda (P72).')
                    ->schema([
                        Textarea::make('message')->label('O que a organização está pedindo')->required()->maxLength(5000)->rows(4),
                        DateTimePicker::make('deadline_at')->label('Prazo (horário de Fortaleza)')->required()->seconds(false)->timezone('America/Fortaleza'),
                    ])
                    ->action(function (array $data, Action $action): void {
                        /** @var Entry $entry */
                        $entry = $this->getOwnerRecord();
                        $this->run($action, fn () => app(RequestProof::class)->handle($entry, auth()->user(), $data), 'Comprovação pedida.');
                    }),
            ])
            ->recordActions([
                Action::make('openProof')
                    ->label('Abrir PDF')
                    ->icon('heroicon-o-document')
                    ->visible(fn (ProofRequest $record): bool => $record->getFirstMedia('proof') !== null)
                    ->url(fn (ProofRequest $record): string => route('admin.proofs.file', $record), shouldOpenInNewTab: true),
                $this->reviewAction('accept', 'Aceitar', ProofRequestStatus::Aceita, 'success'),
                $this->reviewAction('reject', 'Recusar', ProofRequestStatus::Recusada, 'danger'),
            ])
            ->toolbarActions([]);
    }

    private function reviewAction(string $name, string $label, ProofRequestStatus $decision, string $color): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->authorize(fn (ProofRequest $record): bool => auth()->user()?->can('review', $record) ?? false)
            ->requiresConfirmation()
            ->modalDescription('Qualquer consequência (ex.: desclassificar) é uma ação separada (P56).')
            ->action(function (ProofRequest $record, Action $action) use ($decision, $label): void {
                $this->run($action, fn () => app(ReviewProof::class)->handle($record, auth()->user(), $decision), "Comprovação: {$label}.");
            });
    }

    private function run(Action $action, \Closure $callback, string $success): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
            $action->halt();
        }

        Notification::make()->title($success)->success()->send();
    }
}
