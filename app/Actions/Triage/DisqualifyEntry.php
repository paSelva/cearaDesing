<?php

declare(strict_types=1);

namespace App\Actions\Triage;

use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Desclassifica inscrição enviada ou aprovada (§8, docs/07, SPEC-004), com motivo obrigatório
 * (Recomendação). A saída da galeria, votação, avaliação e resultado é tratada nas SPECs
 * dessas funcionalidades, a partir do status.
 */
final class DisqualifyEntry
{
    /** @param array<string, mixed> $input */
    public function handle(Entry $entry, User $admin, array $input): Entry
    {
        if ($admin->role !== Role::Admin) {
            throw ValidationException::withMessages(['entry' => 'Somente a organização faz a triagem.']);
        }

        $validated = Validator::make($input, [
            'reason' => ['required', 'string', 'max:'.config('premio.entry.text_max_length')],
        ], [
            'reason.required' => 'Informe o motivo da desclassificação.',
        ], ['reason' => 'motivo'])->validate();

        $reason = trim($validated['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo da desclassificação.']);
        }

        return DB::transaction(function () use ($entry, $admin, $reason): Entry {
            $locked = Entry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! in_array($from, [EntryStatus::Enviada, EntryStatus::Aprovada], true)) {
                throw ValidationException::withMessages(['entry' => 'Só inscrições enviadas ou aprovadas podem ser desclassificadas.']);
            }

            $locked->status = EntryStatus::Desclassificada;
            $locked->disqualified_at = now();
            $locked->disqualified_by = $admin->id;
            $locked->disqualification_reason = $reason;
            $locked->save();

            activity('triagem')
                ->performedOn($locked)
                ->causedBy($admin)
                ->event('desclassificada')
                ->withProperties(['de' => $from->value, 'para' => EntryStatus::Desclassificada->value, 'motivo' => $reason])
                ->log('Inscrição desclassificada');

            return $locked;
        });
    }
}
