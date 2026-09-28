<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Http\Responses\FailedPasswordResetLinkRequestResponse;

/**
 * E-mail não cadastrado e pedido repetido (throttle, que só ocorre para e-mail existente)
 * recebem a mesma resposta de sucesso, para não revelar quais e-mails têm conta.
 */
final class NeutralPasswordResetLinkResponse extends FailedPasswordResetLinkRequestResponse
{
    public function toResponse($request)
    {
        if (in_array($this->status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return back()->with('status', trans(Password::RESET_LINK_SENT));
        }

        return parent::toResponse($request);
    }
}
