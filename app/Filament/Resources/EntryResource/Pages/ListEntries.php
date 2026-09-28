<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntryResource\Pages;

use App\Actions\Jury\AssignJudgesToCategory;
use App\Filament\Resources\EntryResource;
use App\Models\Category;
use App\Models\Edition;
use App\Models\JudgeAssignment;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEntries extends ListRecords
{
    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Atalho manual da organização (P15): jurados fixos em todas as aprovadas incompletas da categoria.
            Action::make('assignCategory')
                ->label('Atribuir jurados aos aprovados')
                ->icon('heroicon-o-user-group')
                ->authorize(fn (): bool => auth()->user()?->can('create', JudgeAssignment::class) ?? false)
                ->schema([
                    Select::make('category_id')
                        ->label('Categoria')
                        ->options(fn (): array => Category::query()
                            ->whereHas('edition', fn ($query) => $query->where('year', Edition::YEAR))
                            ->orderBy('sort')
                            ->get()
                            ->mapWithKeys(fn (Category $category) => [$category->id => "{$category->code} · {$category->name}"])
                            ->all())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $result = app(AssignJudgesToCategory::class)->handle(Category::query()->findOrFail($data['category_id']), auth()->user());

                    $body = count($result['assigned']).' inscrição(ões) receberam jurados; '
                        .count($result['skipped']).' já estavam completas.';
                    if ($result['failed'] !== []) {
                        $body .= ' Não atribuídas: '.collect($result['failed'])->map(fn (string $why, int $id) => "#{$id} ({$why})")->implode('; ');
                    }

                    Notification::make()
                        ->title('Atribuição concluída')
                        ->body($body)
                        ->{$result['failed'] === [] ? 'success' : 'warning'}()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
