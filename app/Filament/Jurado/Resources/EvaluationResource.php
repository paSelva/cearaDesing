<?php

declare(strict_types=1);

namespace App\Filament\Jurado\Resources;

use App\Enums\AssignmentStatus;
use App\Enums\EntryStatus;
use App\Filament\Jurado\Resources\EvaluationResource\Pages\EvaluateAssignment;
use App\Filament\Jurado\Resources\EvaluationResource\Pages\ListEvaluations;
use App\Filament\Jurado\Resources\EvaluationResource\Pages\ViewEvaluation;
use App\Models\JudgeAssignment;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Avaliações do jurado logado (SPEC-007): só as próprias atribuições ativas de inscrições
 * aprovadas. O jurado vê o projeto com autoria (P13), mas não os dados de conta do
 * participante, nem as notas dos outros jurados, nem a nota final (§8: avaliação independente).
 */
class EvaluationResource extends Resource
{
    protected static ?string $model = JudgeAssignment::class;

    protected static ?string $slug = 'avaliacoes';

    protected static ?string $navigationLabel = 'Minhas avaliações';

    protected static ?string $modelLabel = 'avaliação';

    protected static ?string $pluralModelLabel = 'avaliações';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry.category.code')->label('Cat.'),
                TextColumn::make('entry.category.name')->label('Categoria'),
                TextColumn::make('entry.title')->label('Projeto')->searchable()->limit(60),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (AssignmentStatus $state): string => $state->label())
                    ->color(fn (AssignmentStatus $state): string => $state === AssignmentStatus::Enviada ? 'success' : 'warning'),
                TextColumn::make('submitted_at')->label('Enviada em')->dateTime('d/m/Y H:i', 'America/Fortaleza')->placeholder('—'),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make()->label('Avaliar')->icon('heroicon-o-pencil-square'),
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    /** Conteúdo do projeto mostrado ao jurado (§4, §8, P13). */
    public static function projectComponents(): array
    {
        return [
            Section::make('Projeto')->schema([
                TextEntry::make('entry.category.name')->label('Categoria'),
                TextEntry::make('entry.title')->label('Nome do projeto'),
                TextEntry::make('entry.authorship_credits')->label('Autoria e principais créditos'),
                TextEntry::make('entry.summary')->label('Breve apresentação do projeto'),
                TextEntry::make('entry.context_solution')->label('Contexto e solução desenvolvida'),
                TextEntry::make('entry.results_differentials')->label('Principais resultados ou diferenciais'),
                TextEntry::make('entry.ai_used')->label('Uso relevante de IA generativa (§8)')
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Sim' : 'Não'),
                TextEntry::make('entry.ai_usage_description')->label('Descrição do uso de IA')->placeholder('—'),
                ViewEntry::make('materials')->label('Imagens, PDF e links')->view('filament.jurado.materials'),
            ])->collapsible(),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ...static::projectComponents(),
            Section::make('Minhas notas')->schema([
                ViewEntry::make('my_scores')->hiddenLabel()->view('filament.jurado.my-scores'),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('judge_id', auth()->id())
            ->where('status', '!=', AssignmentStatus::Substituida->value)
            ->whereHas('entry', fn (Builder $query) => $query->where('status', EntryStatus::Aprovada->value))
            ->with(['entry.category', 'scores']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvaluations::route('/'),
            'view' => ViewEvaluation::route('/{record}'),
            'edit' => EvaluateAssignment::route('/{record}/avaliar'),
        ];
    }
}
