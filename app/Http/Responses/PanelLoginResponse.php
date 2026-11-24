<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\RoleHome;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/** Login dos painéis: mesmo critério do site, sem levar à área de outro papel. */
final class PanelLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        return redirect()->to(RoleHome::afterLogin(Filament::auth()->user()));
    }
}
