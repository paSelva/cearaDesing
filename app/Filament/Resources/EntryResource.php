<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Triage\ApproveEntry;
use App\Actions\Triage\DisqualifyEntry;
use App\Enums\ConsentType;
use App\Enums\EntryStatus;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use App\Filament\Resources\EntryResource\Pages\ViewEntry;
use App\Models\Edition;
use App\Models\Entry;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry as ViewInfolistEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Consulta e triagem das inscrições pela organização (SPEC-004). Sem criar nem editar conteúdo. */
class EntryResource extends Resource
{
    protected static ?string $model = Entry::class;

    protected static ?string $slug = 'inscricoes';

    protected static ?string $navigationLabel = 'Inscrições';

    protected static ?string $modelLabel = 'inscrição';

    protected static ?string $pluralModelLabel = 'inscrições';

    protected static string|\UnitEnum|null $navigationGroup = 'Inscrições';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.code')->label('Cat.')->sortable(),
                TextColumn::make('title')->label('Projeto')->placeholder('(sem nome)')->searchable()->limit(50),
                TextColumn::make('user.name')->label('Participante')->searchable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (EntryStatus $state): string => $state->label())
                    ->color(fn (EntryStatus $state): string => match ($state) {
                        EntryStatus::Rascunho => 'gray',
                        EntryStatus::Enviada => 'warning',
                        EntryStatus::Aprovada => 'success',
                        EntryStatus::Desclassificada => 'danger',
                    }),
                TextColumn::make('submitted_at')->label('Enviada em')->dateTime('d/m/Y H:i', 'America/Fortaleza')->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                // Escolha de interface: rascunhos ficam ocultos por padrão (não foram enviados).
                SelectFilter::make('status')->label('Status')->multiple()
                    ->options(collect(EntryStatus::cases())->mapWithKeys(fn (EntryStatus $s) => [$s->value => $s->label()])->all())
                    ->default([EntryStatus::Enviada->value, EntryStatus::Aprovada->value, EntryStatus::Desclassificada->value]),
                SelectFilter::make('category')->label('Categoria')->relationship('category', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                static::approveAction(),
                static::disqualifyAction(),
            ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Inscrição')->schema([
                TextEntry::make('category.name')->label('Categoria')
                    ->formatStateUsing(fn (string $state, Entry $record): string => "{$record->category->code} · {$state}"),
                TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (EntryStatus $state): string => $state->label()),
                TextEntry::make('submitted_at')->label('Enviada em')->dateTime('d/m/Y H:i', 'America/Fortaleza')->placeholder('—'),
                TextEntry::make('approved_at')->label('Aprovada em')->dateTime('d/m/Y H:i', 'America/Fortaleza')->placeholder('—'),
                TextEntry::make('disqualified_at')->label('Desclassificada em')->dateTime('d/m/Y H:i', 'America/Fortaleza')->placeholder('—'),
                TextEntry::make('disqualification_reason')->label('Motivo da desclassificação')->placeholder('—')->columnSpanFull(),
            ])->columns(3),
            Section::make('Participante')->schema([
                TextEntry::make('user.name')->label('Nome'),
                TextEntry::make('user.email')->label('E-mail'),
                TextEntry::make('user.participantProfile.phone')->label('Telefone'),
                TextEntry::make('user.participantProfile.person_type')->label('Tipo de pessoa')
                    ->formatStateUsing(fn ($state): string => $state->label()),
                TextEntry::make('user.participantProfile.document')->label('CPF/CNPJ'),
                TextEntry::make('user.participantProfile.participant_type')->label('Tipo de participante')
                    ->formatStateUsing(fn ($state): string => $state->label()),
                TextEntry::make('user.participantProfile.city')->label('Cidade/UF')
                    ->formatStateUsing(fn (string $state, Entry $record): string => "{$state}/{$record->user->participantProfile->state}"),
            ])->columns(3),
            Section::make('Projeto (§4)')->schema([
                TextEntry::make('title')->label('Nome do projeto')->placeholder('—'),
                TextEntry::make('authorship_credits')->label('Autoria e principais créditos')->placeholder('—'),
                TextEntry::make('summary')->label('Breve apresentação')->placeholder('—'),
                TextEntry::make('context_solution')->label('Contexto e solução desenvolvida')->placeholder('—'),
                TextEntry::make('results_differentials')->label('Principais resultados ou diferenciais')->placeholder('—'),
                TextEntry::make('ai_used')->label('Uso relevante de IA generativa (§8)')
                    ->formatStateUsing(fn (?bool $state): string => match ($state) {
                        true => 'Sim',
                        false => 'Não',
                        null => 'Não informado',
                    }),
                TextEntry::make('ai_usage_description')->label('Descrição do uso de IA')->placeholder('—'),
                RepeatableEntry::make('links')->label('Links')->schema([
                    TextEntry::make('url')->label('URL')->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true),
                    TextEntry::make('label')->label('Descrição')->placeholder('—'),
                ])->columns(2)->placeholder('Nenhum link'),
            ]),
            Section::make('Arquivos')->schema([
                ViewInfolistEntry::make('files')->hiddenLabel()->view('filament.entries.files'),
            ]),
            Section::make('Declarações do §8 (histórico)')->schema([
                RepeatableEntry::make('consents')->hiddenLabel()->schema([
                    TextEntry::make('consent')->label('Item')->formatStateUsing(fn (ConsentType $state): string => $state->label()),
                    TextEntry::make('accepted_at')->label('Data/hora')->dateTime('d/m/Y H:i:s', 'America/Fortaleza'),
                    TextEntry::make('ip')->label('IP'),
                ])->columns(3)->placeholder('Nenhum aceite registrado (rascunho).'),
            ])->collapsed(),
        ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Aprovar')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize(fn (Entry $record): bool => auth()->user()?->can('approve', $record) ?? false)
            ->requiresConfirmation()
            ->modalHeading('Aprovar inscrição')
            ->modalDescription('A inscrição fica travada para edição pelo participante (P70) e passa a valer para a galeria, a votação popular e os jurados.')
            ->action(function (Entry $record, Action $action): void {
                static::runTriage($action, fn () => app(ApproveEntry::class)->handle($record, auth()->user()), 'Inscrição aprovada.');
            });
    }

    public static function disqualifyAction(): Action
    {
        return Action::make('disqualify')
            ->label('Desclassificar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize(fn (Entry $record): bool => auth()->user()?->can('disqualify', $record) ?? false)
            ->modalHeading('Desclassificar inscrição (§8)')
            ->schema([
                Textarea::make('reason')->label('Motivo')->required()->maxLength(5000)->rows(4),
            ])
            ->action(function (Entry $record, array $data, Action $action): void {
                static::runTriage($action, fn () => app(DisqualifyEntry::class)->handle($record, auth()->user(), $data), 'Inscrição desclassificada.');
            });
    }

    private static function runTriage(Action $action, \Closure $callback, string $success): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
            $action->halt();
        }

        Notification::make()->title($success)->success()->send();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('edition', fn (Builder $query) => $query->where('year', Edition::YEAR))
            ->with(['category', 'user.participantProfile']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntries::route('/'),
            'view' => ViewEntry::route('/{record}'),
        ];
    }
}
