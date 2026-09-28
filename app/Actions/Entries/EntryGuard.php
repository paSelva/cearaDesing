<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Enums\EntryStatus;
use App\Models\Edition;
use App\Models\Entry;
use Illuminate\Validation\ValidationException;

/** Regras comuns às Actions de inscrição: período (§7, P77, P8) e status editável (P70). */
final class EntryGuard
{
    public function isRegistrationOpen(Edition $edition): bool
    {
        $now = now();

        return $now->greaterThanOrEqualTo($edition->registration_starts_at)
            && $now->lessThanOrEqualTo($edition->registration_ends_at);
    }

    public function ensureRegistrationOpen(Edition $edition): void
    {
        if ($edition->year !== Edition::YEAR) {
            throw ValidationException::withMessages(['entry' => 'Somente a edição 2026 recebe inscrições.']);
        }

        if (! $this->isRegistrationOpen($edition)) {
            throw ValidationException::withMessages(['entry' => 'O período de inscrições não está aberto.']);
        }
    }

    public function ensureEditable(Entry $entry): void
    {
        $this->ensureRegistrationOpen($entry->edition);
        $this->ensureStatusEditable($entry);
    }

    /**
     * Chamar dentro da transação: trava a linha e confere o status atual no banco.
     * Evita que um salvamento do participante passe por cima de uma aprovação
     * feita no meio da requisição (P70).
     */
    public function lockEditable(Entry $entry): void
    {
        $locked = Entry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
        $entry->status = $locked->status;
        $entry->syncOriginalAttribute('status');

        $this->ensureStatusEditable($entry);
    }

    private function ensureStatusEditable(Entry $entry): void
    {
        if (! $entry->status->isEditableByParticipant()) {
            throw ValidationException::withMessages([
                'entry' => $entry->status === EntryStatus::Aprovada
                    ? 'Inscrição aprovada na triagem não pode mais ser editada.'
                    : 'Esta inscrição não pode ser editada.',
            ]);
        }
    }
}
