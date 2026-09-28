<?php

declare(strict_types=1);

namespace App\Actions\Proofs;

use App\Enums\EntryStatus;
use App\Enums\ProofRequestStatus;
use App\Enums\Role;
use App\Models\Entry;
use App\Models\ProofRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A organização pede comprovação de uma inscrição (§2, §4, P12), com prazo (P56).
 * O status da inscrição não muda (P72).
 */
final class RequestProof
{
    /** @param array<string, mixed> $input */
    public function handle(Entry $entry, User $admin, array $input): ProofRequest
    {
        if ($admin->role !== Role::Admin) {
            throw ValidationException::withMessages(['entry' => 'Somente a organização pede comprovação.']);
        }

        $validated = Validator::make($input, [
            'message' => ['required', 'string', 'max:'.config('premio.entry.text_max_length')],
            // Recomendação: o prazo precisa estar no futuro.
            'deadline_at' => ['required', 'date', 'after:now'],
        ], [
            'deadline_at.after' => 'O prazo precisa ser uma data futura.',
        ], ['message' => 'mensagem', 'deadline_at' => 'prazo'])->validate();

        $message = trim($validated['message']);
        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Informe o que a organização está pedindo.']);
        }

        return DB::transaction(function () use ($entry, $admin, $message, $validated): ProofRequest {
            $locked = Entry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [EntryStatus::Enviada, EntryStatus::Aprovada], true)) {
                throw ValidationException::withMessages(['entry' => 'Só é possível pedir comprovação de inscrições enviadas ou aprovadas.']);
            }

            $request = new ProofRequest;
            $request->entry_id = $locked->id;
            $request->requested_by = $admin->id;
            $request->message = $message;
            $request->deadline_at = Carbon::parse($validated['deadline_at'], config('app.timezone'));
            $request->status = ProofRequestStatus::Pendente;
            $request->is_late = false;
            $request->save();

            activity('comprovacao')
                ->performedOn($locked)
                ->causedBy($admin)
                ->event('solicitada')
                ->withProperties(['proof_request_id' => $request->id, 'prazo' => $request->deadline_at->toDateTimeString()])
                ->log('Comprovação solicitada');

            return $request;
        });
    }
}
