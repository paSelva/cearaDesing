<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EditionResource\Pages\EditEdition;
use App\Filament\Resources\EditionResource\Pages\ListEditions;
use App\Models\Edition;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EditionResource extends Resource
{
    protected static ?string $model = Edition::class;

    protected static ?string $navigationLabel = 'Configuração da edição';

    protected static ?string $modelLabel = 'Edição 2026';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuração';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Inscrições')->schema([
                TextInput::make('name')->label('Nome da edição')->required()->maxLength(255),
                DateTimePicker::make('registration_starts_at')->label('Início das inscrições')->required()->seconds(true)->timezone('America/Fortaleza'),
                DateTimePicker::make('registration_ends_at')->label('Encerramento das inscrições')->required()->seconds(true)->timezone('America/Fortaleza'),
                DatePicker::make('project_period_starts_on')->label('Início da elegibilidade dos projetos')->required(),
                DatePicker::make('project_period_ends_on')->label('Fim da elegibilidade dos projetos')->required(),
            ])->columns(2),
            Section::make('Avaliação e votação')->schema([
                DateTimePicker::make('judging_starts_at')->label('Início da avaliação')->required()->seconds(true)->timezone('America/Fortaleza'),
                DateTimePicker::make('judging_ends_at')->label('Fim da avaliação')->required()->seconds(true)->timezone('America/Fortaleza'),
                DateTimePicker::make('voting_starts_at')->label('Início da votação')->required()->seconds(true)->timezone('America/Fortaleza'),
                DateTimePicker::make('voting_ends_at')->label('Fim da votação')->required()->seconds(true)->timezone('America/Fortaleza'),
                TextInput::make('judges_per_entry')->label('Jurados por inscrição')->required()->numeric()->integer()->minValue(1)->maxValue(255),
            ])->columns(2),
            Section::make('Resultado e cerimônia')->schema([
                DatePicker::make('results_announce_on')->label('Divulgação informativa')->required(),
                DatePicker::make('ceremony_on')->label('Cerimônia')->required(),
            ])->columns(2),
            Section::make('Faixas de distinção (pontos mínimos)')->schema([
                TextInput::make('band_gold_min')->label('Ouro')->required()->numeric()->integer()->minValue(1)->maxValue(255),
                TextInput::make('band_silver_min')->label('Prata')->required()->numeric()->integer()->minValue(1)->maxValue(255),
                TextInput::make('band_bronze_min')->label('Bronze')->required()->numeric()->integer()->minValue(1)->maxValue(255),
                TextInput::make('band_honorable_min')->label('Menção honrosa')->required()->numeric()->integer()->minValue(1)->maxValue(255),
            ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Edição')->searchable(),
                TextColumn::make('year')->label('Ano'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('year', Edition::YEAR);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEditions::route('/'),
            'edit' => EditEdition::route('/{record}/edit'),
        ];
    }
}
