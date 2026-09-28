<?php

declare(strict_types=1);

namespace App\Http\Controllers\Participant;

use App\Actions\Proofs\SubmitProof;
use App\Http\Controllers\Controller;
use App\Http\Responses\PrivateMediaResponse;
use App\Models\ProofRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProofController extends Controller
{
    public function store(Request $request, ProofRequest $proofRequest, SubmitProof $action): RedirectResponse
    {
        Gate::authorize('view', $proofRequest);
        $action->handle($proofRequest, $request->user(), $request->file('proof'));

        return back()->with('status', 'Comprovação enviada. A organização vai analisar.');
    }

    public function file(ProofRequest $proofRequest): StreamedResponse
    {
        Gate::authorize('view', $proofRequest);

        return PrivateMediaResponse::make($proofRequest, $proofRequest->getFirstMedia('proof') ?? abort(404));
    }
}
