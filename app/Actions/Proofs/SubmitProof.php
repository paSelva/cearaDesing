<?php

declare(strict_types=1);

namespace App\Actions\Proofs;

use App\Enums\ProofRequestStatus;
use App\Models\ProofRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * O participante envia 1 PDF de até 10 MB (P54). Envio depois do prazo é aceito e
 * marcado como atrasado (P79). Não depende do período de inscrições nem do status
 * da inscrição (P72).
 */
final class SubmitProof
{
    public function handle(ProofRequest $proofRequest, User $participant, mixed $file): ProofRequest
    {
        if ($proofRequest->entry->user_id !== $participant->id) {
            throw ValidationException::withMessages(['proof' => 'Esta solicitação não é da sua inscrição.']);
        }

        Validator::make(['proof' => $file], [
            'proof' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ], [
            'proof.max' => 'O PDF pode ter no máximo 10 MB.',
            'proof.mimetypes' => 'Envie um arquivo PDF.',
            'proof.uploaded' => 'O PDF não foi recebido. O arquivo pode ter no máximo 10 MB.',
        ], ['proof' => 'comprovação'])->validate();

        return DB::transaction(function () use ($proofRequest, $file): ProofRequest {
            $locked = ProofRequest::query()->whereKey($proofRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ProofRequestStatus::Pendente) {
                throw ValidationException::withMessages(['proof' => 'Esta comprovação já foi enviada.']);
            }

            /** @var UploadedFile $file */
            $locked->addMedia($file)
                ->usingName(Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 200, ''))
                ->usingFileName(Str::uuid().'.pdf')
                ->toMediaCollection('proof');

            $now = now();
            $locked->status = ProofRequestStatus::Respondida;
            $locked->responded_at = $now;
            $locked->is_late = $now->greaterThan($locked->deadline_at);
            $locked->save();

            return $locked;
        });
    }
}
