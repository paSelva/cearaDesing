<?php

declare(strict_types=1);

namespace App\Actions\Triage;

use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Triagem: aprova a inscrição enviada (docs/07, SPEC-004). Pode ocorrer durante o período
 * de inscrições (P70); a aprovação trava a edição pelo participante e libera o projeto para
 * a galeria/votação (P37, P43) e para os jurados (P76) — funcionalidades de SPECs futuras.
 */
final class ApproveEntry
{
    public function handle(Entry $entry, User $admin): Entry
    {
        if ($admin->role !== Role::Admin) {
            throw ValidationException::withMessages(['entry' => 'Somente a organização faz a triagem.']);
        }

        return DB::transaction(function () use ($entry, $admin): Entry {
            $locked = Entry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== EntryStatus::Enviada) {
                throw ValidationException::withMessages(['entry' => 'Só inscrições enviadas podem ser aprovadas.']);
            }

            $locked->status = EntryStatus::Aprovada;
            $locked->approved_at = now();
            $locked->approved_by = $admin->id;
            $locked->save();

            activity('triagem')
                ->performedOn($locked)
                ->causedBy($admin)
                ->event('aprovada')
                ->withProperties(['de' => EntryStatus::Enviada->value, 'para' => EntryStatus::Aprovada->value])
                ->log('Inscrição aprovada na triagem');

            return $locked;
        });
    }
}
