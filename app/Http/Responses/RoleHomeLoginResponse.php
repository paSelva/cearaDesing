<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\RoleHome;
use Laravel\Fortify\Contracts\LoginResponse;

/** Depois do login no site, cada papel vai para a própria área (SPEC-009). */
final class RoleHomeLoginResponse implements LoginResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return redirect()->to(RoleHome::afterLogin($request->user()));
    }
}
