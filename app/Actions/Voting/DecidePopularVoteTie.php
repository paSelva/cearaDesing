<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\Role;
use App\Models\PopularVoteWinner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** A organização decide o empate do voto popular numa categoria (P49), entre os empatados. */
final class DecidePopularVoteTie
{
    /** @param array<string, mixed> $input entry_id, decision_note */
    public function handle(PopularVoteWinner $winner, User $admin, array $input): PopularVoteWinner
    {
        if ($admin->role !== Role::Admin) {
            throw ValidationException::withMessages(['entry_id' => 'Somente a organização decide o empate.']);
        }

        $validated = Validator::make($input, [
            'entry_id' => ['required', 'integer'],
            'decision_note' => ['nullable', 'string', 'max:'.config('premio.entry.text_max_length')],
        ], [], ['entry_id' => 'projeto', 'decision_note' => 'observação'])->validate();

        return DB::transaction(function () use ($winner, $admin, $validated): PopularVoteWinner {
            $locked = PopularVoteWinner::query()->whereKey($winner->id)->lockForUpdate()->firstOrFail();

            if (! $locked->was_tie) {
                throw ValidationException::withMessages(['entry_id' => 'Não há empate nesta categoria.']);
            }

            if (! in_array((int) $validated['entry_id'], $locked->tied_entry_ids ?? [], true)) {
                throw ValidationException::withMessages(['entry_id' => 'Escolha um dos projetos empatados.']);
            }

            $locked->entry_id = (int) $validated['entry_id'];
            $locked->decided_by = $admin->id;
            $locked->decision_note = $validated['decision_note'] ?? null;
            $locked->save();

            activity('voto_popular')
                ->performedOn($locked)
                ->causedBy($admin)
                ->event('desempate')
                ->withProperties(['entry_id' => $locked->entry_id, 'empatados' => $locked->tied_entry_ids])
                ->log('Empate do voto popular decidido pela organização');

            return $locked;
        });
    }
}
