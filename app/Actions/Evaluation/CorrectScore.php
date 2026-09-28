<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Score;
use App\Models\ScoreRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Correção de nota pela organização em caso de erro (§8, P19), com histórico e justificativa
 * obrigatória (P46). Recalcula o resultado. Sem aviso ao participante (P80).
 */
final class CorrectScore
{
    public function __construct(private readonly CalculateEntryResult $calculate) {}

    /** @param array<string, mixed> $input new_value, reason */
    public function handle(Score $score, User $admin, array $input): Score
    {
        if ($admin->role !== Role::Admin) {
            throw ValidationException::withMessages(['new_value' => 'Somente a organização corrige notas.']);
        }

        $validated = Validator::make($input, [
            'new_value' => ['required', 'integer', 'between:1,5'],
            'reason' => ['required', 'string', 'max:'.config('premio.entry.text_max_length')],
        ], [
            'new_value.between' => 'A nota deve ser de 1 a 5 (§5).',
            'reason.required' => 'A justificativa é obrigatória (P46).',
        ], ['new_value' => 'nova nota', 'reason' => 'justificativa'])->validate();

        $reason = trim($validated['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A justificativa é obrigatória (P46).']);
        }

        return DB::transaction(function () use ($score, $admin, $validated, $reason): Score {
            $locked = Score::query()->whereKey($score->id)->lockForUpdate()->firstOrFail();

            if ($locked->assignment->status !== AssignmentStatus::Enviada) {
                throw ValidationException::withMessages(['new_value' => 'Só notas de avaliações enviadas (e não substituídas) são corrigidas pela organização.']);
            }

            $new = (int) $validated['new_value'];
            if ($new === $locked->value) {
                throw ValidationException::withMessages(['new_value' => 'A nova nota é igual à atual.']);
            }

            $revision = new ScoreRevision;
            $revision->score_id = $locked->id;
            $revision->old_value = $locked->value;
            $revision->new_value = $new;
            $revision->reason = $reason;
            $revision->changed_by = $admin->id;
            $revision->save();

            $locked->value = $new;
            $locked->save();

            $entry = $locked->assignment->entry;
            $this->calculate->handle($entry);

            activity('juri')
                ->performedOn($entry)
                ->causedBy($admin)
                ->event('correcao_de_nota')
                ->withProperties(['score_id' => $locked->id, 'de' => $revision->old_value, 'para' => $new, 'motivo' => $reason])
                ->log('Nota corrigida pela organização');

            return $locked;
        });
    }
}
