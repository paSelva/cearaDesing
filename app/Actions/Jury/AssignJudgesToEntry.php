<?php

declare(strict_types=1);

namespace App\Actions\Jury;

use App\Enums\AssignmentStatus;
use App\Enums\EntryStatus;
use App\Models\Entry;
use App\Models\JudgeAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Atribui jurados a uma inscrição aprovada (P15, P76). Por padrão, os jurados fixos da
 * categoria (P14). Completa até `judges_per_entry` atribuições não substituídas (§5),
 * pulando quem já está (ou já esteve) na inscrição.
 */
final class AssignJudgesToEntry
{
    public function __construct(private readonly JuryGuard $guard) {}

    /**
     * @param  list<int>|null  $judgeIds  null = jurados fixos da categoria
     * @return Collection<int, JudgeAssignment> atribuições criadas
     */
    public function handle(Entry $entry, User $admin, ?array $judgeIds = null): Collection
    {
        $this->guard->ensureAdmin($admin);

        return DB::transaction(function () use ($entry, $admin, $judgeIds): Collection {
            $locked = Entry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== EntryStatus::Aprovada) {
                throw ValidationException::withMessages(['judges' => 'Só inscrições aprovadas na triagem recebem jurados (P76).']);
            }

            $required = $locked->edition->judges_per_entry;
            $existing = $locked->judgeAssignments()->get();
            $active = $existing->where('status', '!=', AssignmentStatus::Substituida)->count();
            $missing = $required - $active;

            if ($missing <= 0) {
                throw ValidationException::withMessages(['judges' => "A inscrição já tem os {$required} jurados."]);
            }

            $candidates = $judgeIds === null
                ? $locked->category->judges()->pluck('users.id')->all()
                : $this->guard->ensureJudges($judgeIds);

            $alreadyOnEntry = $existing->pluck('judge_id')->all();
            $toAssign = array_slice(array_values(array_diff($candidates, $alreadyOnEntry)), 0, $missing);

            if (count($toAssign) < $missing) {
                throw ValidationException::withMessages([
                    'judges' => $judgeIds === null
                        ? "A categoria não tem jurados fixos suficientes: faltam {$missing} jurado(s) para esta inscrição. Defina os jurados da categoria ou substitua manualmente."
                        : "Selecione {$missing} jurado(s) que ainda não estejam nesta inscrição.",
                ]);
            }

            $created = collect($toAssign)->map(function (int $judgeId) use ($locked, $admin): JudgeAssignment {
                $assignment = new JudgeAssignment;
                $assignment->entry_id = $locked->id;
                $assignment->judge_id = $judgeId;
                $assignment->assigned_by = $admin->id;
                $assignment->status = AssignmentStatus::Pendente;
                $assignment->save();

                return $assignment;
            });

            activity('juri')
                ->performedOn($locked)
                ->causedBy($admin)
                ->event('atribuicao')
                ->withProperties(['jurados' => $toAssign])
                ->log('Jurados atribuídos à inscrição');

            return $created;
        });
    }
}
