<?php

declare(strict_types=1);

namespace App\Actions\Proofs;

use App\Enums\ProofRequestStatus;
use App\Enums\Role;
use App\Models\ProofRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A organização aceita ou recusa a comprovação enviada. Qualquer consequência
 * (ex.: desclassificar) é uma ação separada (P56).
 */
final class ReviewProof
{
    public function handle(ProofRequest $proofRequest, User $admin, ProofRequestStatus $decision): ProofRequest
    {
        if ($admin->role !== Role::Admin) {
            throw ValidationException::withMessages(['proof' => 'Somente a organização analisa comprovações.']);
        }

        if (! in_array($decision, [ProofRequestStatus::Aceita, ProofRequestStatus::Recusada], true)) {
            throw ValidationException::withMessages(['proof' => 'Decisão inválida.']);
        }

        return DB::transaction(function () use ($proofRequest, $admin, $decision): ProofRequest {
            $locked = ProofRequest::query()->whereKey($proofRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ProofRequestStatus::Respondida) {
                throw ValidationException::withMessages(['proof' => 'Só comprovações enviadas e ainda não analisadas podem ser avaliadas.']);
            }

            $locked->status = $decision;
            $locked->reviewed_at = now();
            $locked->reviewed_by = $admin->id;
            $locked->save();

            activity('comprovacao')
                ->performedOn($locked->entry)
                ->causedBy($admin)
                ->event($decision->value)
                ->withProperties(['proof_request_id' => $locked->id, 'atrasada' => $locked->is_late])
                ->log('Comprovação analisada');

            return $locked;
        });
    }
}
