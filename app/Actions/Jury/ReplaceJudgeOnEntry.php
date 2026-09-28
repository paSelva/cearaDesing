<?php

declare(strict_types=1);

namespace App\Actions\Jury;

use App\Enums\AssignmentStatus;
use App\Enums\EntryStatus;
use App\Models\JudgeAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Substitui um jurado numa inscrição por conflito (§8, P16), por qualquer jurado escolhido
 * pela organização (P47). As notas antigas ficam para histórico e deixam de contar.
 * O recálculo do resultado entra na SPEC-007 (ainda não há resultado calculado).
 */
final class ReplaceJudgeOnEntry
{
    public function __construct(private readonly JuryGuard $guard) {}

    /** @param array<string, mixed> $input  new_judge_id, reason */
    public function handle(JudgeAssignment $assignment, User $admin, array $input): JudgeAssignment
    {
        $this->guard->ensureAdmin($admin);

        $validated = Validator::make($input, [
            'new_judge_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:'.config('premio.entry.text_max_length')],
        ], [
            'reason.required' => 'Informe o motivo da substituição.',
            'new_judge_id.required' => 'Escolha o jurado substituto.',
        ], ['reason' => 'motivo', 'new_judge_id' => 'jurado substituto'])->validate();

        $reason = trim($validated['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo da substituição.']);
        }

        [$newJudgeId] = $this->guard->ensureJudges([(int) $validated['new_judge_id']], 'new_judge_id');

        return DB::transaction(function () use ($assignment, $admin, $newJudgeId, $reason): JudgeAssignment {
            $locked = JudgeAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === AssignmentStatus::Substituida) {
                throw ValidationException::withMessages(['new_judge_id' => 'Este jurado já foi substituído.']);
            }

            if ($locked->entry->status !== EntryStatus::Aprovada) {
                throw ValidationException::withMessages(['new_judge_id' => 'Só é possível substituir jurados de inscrições aprovadas.']);
            }

            if ($locked->entry->judgeAssignments()->where('judge_id', $newJudgeId)->exists()) {
                throw ValidationException::withMessages(['new_judge_id' => 'Este jurado já está (ou já esteve) nesta inscrição.']);
            }

            $locked->status = AssignmentStatus::Substituida;
            $locked->replaced_at = now();
            $locked->replaced_by = $admin->id;
            $locked->replacement_reason = $reason;
            $locked->save();

            $replacement = new JudgeAssignment;
            $replacement->entry_id = $locked->entry_id;
            $replacement->judge_id = $newJudgeId;
            $replacement->assigned_by = $admin->id;
            $replacement->status = AssignmentStatus::Pendente;
            $replacement->save();

            activity('juri')
                ->performedOn($locked->entry)
                ->causedBy($admin)
                ->event('substituicao')
                ->withProperties(['de' => $locked->judge_id, 'para' => $newJudgeId, 'motivo' => $reason])
                ->log('Jurado substituído na inscrição');

            return $replacement;
        });
    }
}
