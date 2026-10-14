<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\PrivateMediaResponse;
use App\Models\ProofRequest;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProofFileController extends Controller
{
    public function __invoke(ProofRequest $proofRequest): StreamedResponse
    {
        Gate::authorize('view', $proofRequest);

        return PrivateMediaResponse::make($proofRequest, $proofRequest->getFirstMedia('proof') ?? abort(404));
    }
}
