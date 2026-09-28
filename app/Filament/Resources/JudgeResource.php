<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Admin\SetJudgePassword;
use App\Enums\Role;
use App\Filament\Resources\JudgeResource\Pages\CreateJudge;
use App\Filament\Resources\JudgeResource\Pages\ListJudges;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JudgeResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'jurados';

    protected static ?string $navigationLabel = 'Jurados';

    protected static ?string $modelLabel = 'jurado';

    protected static ?string $pluralModelLabel = 'jurados';

    protected static string|\UnitEnum|null $navigationGroup = 'Júri';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(255),
            TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255),
            TextInput::make('password')->label('Senha')->password()->revealable()->required()->minLength(12)->confirmed(),
            TextInput::make('password_confirmation')->label('Confirme a senha')->password()->revealable()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i', 'America/Fortaleza'),
            ])
            ->recordActions([
                Action::make('changePassword')
                    ->label('Alterar senha')
                    ->icon('heroicon-o-key')
                    ->authorize(fn (User $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->modalHeading(fn (User $record): string => "Nova senha para {$record->name}")
                    ->modalDescription('A senha atual deixa de valer. Repasse a nova senha ao jurado por um canal seguro.')
                    ->schema([
                        TextInput::make('password')->label('Nova senha')->password()->revealable()->required()->minLength(12)->confirmed(),
                        TextInput::make('password_confirmation')->label('Confirme a nova senha')->password()->revealable()->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        app(SetJudgePassword::class)->handle($record, $data);

                        Notification::make()->title('Senha alterada.')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', Role::Jurado->value);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJudges::route('/'),
            'create' => CreateJudge::route('/create'),
        ];
    }
}
