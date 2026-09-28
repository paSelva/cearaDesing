<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voter;

use App\Actions\Voting\RegisterVoter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Cadastro de eleitor em página própria (T1, P60), separado do cadastro de participante. */
final class RegisterVoterController extends Controller
{
    public function create(): View
    {
        return view('voter.register', ['siteKey' => config('services.turnstile.site_key')]);
    }

    public function store(Request $request, RegisterVoter $action): RedirectResponse
    {
        $user = $action->handle(
            $request->only(['name', 'email', 'password', 'password_confirmation', 'cpf', 'cf-turnstile-response']),
            (string) $request->ip(),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('voter.home')->with('status', 'Cadastro de eleitor concluído.');
    }
}
