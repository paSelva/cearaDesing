<?php

declare(strict_types=1);

namespace App\Filament\Jurado\Resources\EvaluationResource\Pages;

use App\Actions\Evaluation\SaveScores;
use App\Actions\Evaluation\SubmitEvaluation;
use App\Filament\Jurado\Resources\EvaluationResource;
use App\Models\JudgeAssignment;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Tela de notas: 4 notas de 1 a 5 (§5), sem comentário (P18). "Salvar" guarda parciais;
 * "Salvar e enviar" salva primeiro e depois envia, travando as notas (P19) — o que foi
 * marcado nunca se perde se o envio falhar.
 */
class EvaluateAssignment extends EditRecord
{
    protected static string $resource = EvaluationResource::class;

    protected static ?string $title = 'Avaliar projeto';

    private bool $submitting = false;

    private const SCALE = [
        1 => '1 — Insuficiente',
        2 => '2 — Regular',
        3 => '3 — Bom',
        4 => '4 — Muito bom',
        5 => '5 — Excelente',
    ];

    public function form(Schema $schema): Schema
    {
        /** @var JudgeAssignment $assignment */
        $assignment = $this->getRecord();
        $criteria = $assignment->entry->category->criteria;

        return $schema->components([
            ...EvaluationResource::projectComponents(),
            Section::make('Notas (§5)')
                ->description('Uma nota de 1 a 5 para cada critério. Depois de enviar, as notas não podem mais ser alteradas.')
                ->schema($criteria->map(fn ($criterion) => Radio::make("scores.{$criterion->id}")
                    ->label($criterion->name)
                    ->helperText($criterion->description)
                    ->options(self::SCALE)
                    ->inline())->all()),
        ])->columns(1);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var JudgeAssignment $assignment */
        $assignment = $this->getRecord();

        return ['scores' => $assignment->scores()->pluck('value', 'criterion_id')->map(fn ($value) => (string) $value)->all()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var JudgeAssignment $record */
        try {
            app(SaveScores::class)->handle($record, auth()->user(), $data['scores'] ?? []);
        } catch (ValidationException $e) {
            $this->fail($e);
        }

        if ($this->submitting) {
            try {
                app(SubmitEvaluation::class)->handle($record, auth()->user());
            } catch (ValidationException $e) {
                // As notas já foram salvas; só o envio não aconteceu.
                $this->fail($e, 'Notas salvas, mas a avaliação ainda não foi enviada: ');
            }
        }

        return $record->refresh();
    }

    private function fail(ValidationException $e, string $prefix = ''): never
    {
        Notification::make()->title($prefix.collect($e->errors())->flatten()->first())->danger()->persistent()->send();

        throw new Halt;
    }

    public function submitEvaluation(): void
    {
        $this->submitting = true;
        $this->save();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->submitting ? 'Avaliação enviada. As notas estão travadas.' : 'Notas salvas.';
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->submitting ? EvaluationResource::getUrl('view', ['record' => $this->getRecord()]) : null;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Salvar notas'),
            Action::make('submitEvaluation')
                ->label('Salvar e enviar avaliação')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Enviar avaliação?')
                ->modalDescription('Depois de enviada, você não poderá mais alterar as notas (P19).')
                ->action(fn () => $this->submitEvaluation()),
            $this->getCancelFormAction(),
        ];
    }
}
