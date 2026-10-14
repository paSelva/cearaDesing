<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Enums\EntryStatus;
use App\Enums\ParticipantType;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/** Cria a inscrição em rascunho numa categoria (docs/07, SPEC-003). */
final class CreateEntryDraft
{
    public function __construct(private readonly EntryGuard $guard) {}

    public function handle(User $participant, Category $category): Entry
    {
        if ($participant->role !== Role::Participante || $participant->participantProfile === null) {
            throw ValidationException::withMessages(['category' => 'Somente participantes podem inscrever projetos.']);
        }

        $edition = $category->edition;
        $this->guard->ensureRegistrationOpen($edition);

        $this->ensureStudentRule($participant, $category);

        if ($participant->entries()->where('category_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => 'Você já tem uma inscrição nesta categoria.']);
        }

        try {
            $entry = new Entry;
            $entry->edition_id = $edition->id;
            $entry->category_id = $category->id;
            $entry->user_id = $participant->id;
            $entry->status = EntryStatus::Rascunho;
            $entry->save();

            return $entry;
        } catch (UniqueConstraintViolationException) {
            // Corrida entre requisições: o índice único decide (§4).
            throw ValidationException::withMessages(['category' => 'Você já tem uma inscrição nesta categoria.']);
        }
    }

    /** 3.7 é exclusiva para estudantes e estudantes só concorrem na 3.7 (P9). */
    private function ensureStudentRule(User $participant, Category $category): void
    {
        $isStudent = $participant->participantProfile->participant_type === ParticipantType::Estudante;

        if ($category->is_student_only && ! $isStudent) {
            throw ValidationException::withMessages(['category' => 'Esta categoria é exclusiva para estudantes.']);
        }

        if (! $category->is_student_only && $isStudent) {
            throw ValidationException::withMessages(['category' => 'Estudantes concorrem somente na categoria exclusiva para estudantes.']);
        }
    }
}
