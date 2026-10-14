<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Valida o token do Cloudflare Turnstile no servidor (P52). Falha fechada: sem chave
 * secreta configurada, sem resposta ou com erro, o cadastro é recusado.
 */
final class Turnstile implements ValidationRule
{
    public function __construct(private readonly ?string $ip = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = (string) config('services.turnstile.secret_key');

        if ($secret === '' || ! is_string($value) || $value === '') {
            $fail('Confirme que você não é um robô.');

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post((string) config('services.turnstile.verify_url'), array_filter([
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => $this->ip,
                ]));
        } catch (Throwable) {
            $fail('Não foi possível confirmar a verificação. Tente novamente.');

            return;
        }

        if (! $response->ok() || $response->json('success') !== true) {
            $fail('A verificação falhou. Tente novamente.');
        }
    }
}
