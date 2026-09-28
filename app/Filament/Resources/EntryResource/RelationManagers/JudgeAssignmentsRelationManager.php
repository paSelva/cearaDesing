<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntryResource\RelationManagers;

use App\Actions\Jury\AssignJudgesToEntry;
use App\Actions\Jury\ReplaceJudgeOnEntry;
use App\Enums\AssignmentStatus;
use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Models\Entry;
use App\Models\JudgeAssignment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/** Jurados da inscrição: atribuição (P15, P76) e substituição por conflito (P16, P47) — SPEC-006. */
class JudgeAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'judgeAssignments';

    protected static ?string $title = 'Jurados';

    protected static ?string $modelLabel = 'atribuição';

    protected static ?string $pluralModelLabel = 'atribuições';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judge.name')->label('Jurado'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (AssignmentStatus $state): string => $state->label())
                    ->color(fn (AssignmentStatus $state): string => match ($state) {
                        AssignmentStatus::Pendente => 'warning',
                        AssignmentStatus::Enviada => 'success',
                        AssignmentStatus::Substituida => 'gray',
                    }),
                TextColumn::make('submitted_at')->label('Enviada em')->dateTime('d/m/Y H:i', 'America/Fortaleza')->placeholder('—'),
                TextColumn::make('replacement_reason')->label('Motivo da substituição')->placeholder('—')->wrap(),
            ])
            ->defaultSort('id')
            ->headerActions([
                Action::make('assignJudges')
                    ->label('Atribuir jurados da categoria')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === EntryStatus::Aprovada)
                    ->requiresConfirmation()
                    ->modalDescription('Completa a inscrição com os jurados fixos da categoria (P14, P15).')
                    ->action(function (Action $action): void {
                        /** @var Entry $entry */
                        $entry = $this->getOwnerRecord();
                        $this->run($action, fn () => app(AssignJudgesToEntry::class)->handle($entry, auth()->user()), 'Jurados atribuídos.');
                    }),
            ])
            ->recordActions([
                Action::make('replace')
                    ->label('Substituir')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->authorize(fn (JudgeAssignment $record): bool => auth()->user()?->can('replace', $record) ?? false)
                    ->modalHeading('Substituir jurado nesta inscrição (conflito, §8)')
                    ->modalDescription('As notas já dadas ficam no histórico e deixam de contar.')
                    ->schema(fn (JudgeAssignment $record): array => [
                        Select::make('new_judge_id')
                            ->label('Jurado substituto')
                            ->options(fn (): array => User::query()
                                ->where('role', Role::Jurado->value)
                                ->whereNotIn('id', $record->entry->judgeAssignments()->pluck('judge_id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        Textarea::make('reason')->label('Motivo')->required()->maxLength(5000)->rows(3),
                    ])
                    ->action(function (JudgeAssignment $record, array $data, Action $action): void {
                        $this->run($action, fn () => app(ReplaceJudgeOnEntry::class)->handle($record, auth()->user(), $data), 'Jurado substituído.');
                    }),
            ])
            ->toolbarActions([]);
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
