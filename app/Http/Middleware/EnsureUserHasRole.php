<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso: `->middleware('role:participante')`. Não substitui as Policies. */
final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->user()?->role === Role::from($role), 403);

        return $next($request);
    }
}
