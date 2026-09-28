<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Jury\SetCategoryJudges;
use App\Enums\Role;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationLabel = 'Categorias';

    protected static ?string $pluralModelLabel = 'Categorias';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->sortable(),
                TextColumn::make('name')->label('Categoria')->searchable(),
                TextColumn::make('description')->label('Descrição')->limit(90)->wrap(),
                IconColumn::make('is_student_only')->label('Exclusiva para estudantes')->boolean(),
                TextColumn::make('sort')->label('Ordem')->sortable(),
                TextColumn::make('judges.name')->label('Jurados fixos (P14)')->listWithLineBreaks()->placeholder('Nenhum'),
            ])
            ->defaultSort('sort')
            ->recordActions([
                // SPEC-006: os dados do catálogo continuam somente leitura; esta ação só define o júri.
                Action::make('setJudges')
                    ->label('Definir jurados')
                    ->icon('heroicon-o-user-group')
                    ->visible(fn (): bool => auth()->user()?->role === Role::Admin)
                    ->fillForm(fn (Category $record): array => ['judges' => $record->judges()->pluck('users.id')->all()])
                    ->schema(fn (Category $record): array => [
                        Select::make('judges')
                            ->label('Jurados fixos da categoria')
                            ->helperText("Até {$record->edition->judges_per_entry} jurados. Mudar a lista não altera atribuições já feitas.")
                            ->multiple()
                            ->options(fn (): array => User::query()->where('role', Role::Jurado->value)->orderBy('name')->pluck('name', 'id')->all())
                            ->maxItems($record->edition->judges_per_entry)
                            ->searchable(),
                    ])
                    ->action(function (Category $record, array $data, Action $action): void {
                        try {
                            app(SetCategoryJudges::class)->handle($record, array_map('intval', $data['judges'] ?? []), auth()->user());
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                            $action->halt();
                        }

                        Notification::make()->title('Jurados da categoria salvos.')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('edition', fn (Builder $query): Builder => $query->where('year', 2026));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
        ];
    }
}
