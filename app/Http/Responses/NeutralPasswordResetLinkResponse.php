<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Http\Responses\FailedPasswordResetLinkRequestResponse;

/**
 * E-mail não cadastrado recebe a mesma resposta de sucesso,
 * para a recuperação de senha não revelar quais e-mails têm conta.
 */
final class NeutralPasswordResetLinkResponse extends FailedPasswordResetLinkRequestResponse
{
    public function toResponse($request)
    {
        if ($this->status === Password::INVALID_USER) {
            return back()->with('status', trans(Password::RESET_LINK_SENT));
        }

        return parent::toResponse($request);
    }
}
