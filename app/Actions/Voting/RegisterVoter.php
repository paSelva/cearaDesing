<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\Role;
use App\Models\User;
use App\Models\VoterProfile;
use App\Rules\Cpf;
use App\Rules\Turnstile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de eleitor do voto popular (docs/07, SPEC-009): CPF válido pelo dígito verificador
 * (P42), 1 cadastro por CPF via hash (P35), e-mail único em users (T4, P60), Turnstile e limite
 * de 5 cadastros por IP por hora (P50, P52). Sem confirmação de e-mail (P42).
 */
final class RegisterVoter
{
    public const MAX_PER_IP_PER_HOUR = 5;

    /** @param array<string, mixed> $input */
    public function handle(array $input, string $ip): User
    {
        $key = (string) config('services.cpf_hmac.key');
        if ($key === '') {
            // Falha fechada: sem a chave não há como garantir 1 cadastro por CPF.
            throw ValidationException::withMessages(['cpf' => 'O cadastro de eleitor está indisponível no momento.']);
        }

        $limiterKey = 'voter-register:'.$ip;
        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_PER_IP_PER_HOUR)) {
            throw ValidationException::withMessages(['cpf' => 'Muitos cadastros feitos a partir desta rede. Tente novamente em '.ceil(RateLimiter::availableIn($limiterKey) / 60).' minutos.']);
        }

        $input['cpf'] = Cpf::normalize(is_string($input['cpf'] ?? null) ? $input['cpf'] : '');
        $input['email'] = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        $hash = hash_hmac('sha256', $input['cpf'], $key);

        $validated = Validator::make($input + ['cpf_hash' => $hash], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'cpf' => ['required', 'string', new Cpf],
            'cpf_hash' => [Rule::unique('voter_profiles', 'cpf_hash')],
            // `required`: regras customizadas não rodam em campo vazio.
            'cf-turnstile-response' => ['required', new Turnstile($ip)],
        ], [
            'email.unique' => 'Este e-mail já tem uma conta. Para votar, use outro e-mail (o cadastro de eleitor é separado).',
            'cpf_hash.unique' => 'Este CPF já tem cadastro de eleitor.',
            'cf-turnstile-response.required' => 'Confirme que você não é um robô.',
        ], ['cpf' => 'CPF', 'cf-turnstile-response' => 'verificação'])->validate();

        try {
            $user = DB::transaction(function () use ($validated, $hash, $ip): User {
                $user = new User([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                ]);
                $user->role = Role::Eleitor;
                $user->save();

                $profile = new VoterProfile;
                $profile->user_id = $user->id;
                $profile->cpf_hash = $hash;
                $profile->cpf_encrypted = $validated['cpf'];
                $profile->registration_ip = $ip;
                $profile->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['cpf' => 'Este CPF ou e-mail já tem cadastro.']);
        }

        // Conta só cadastros concluídos (erros de digitação não bloqueiam a rede).
        RateLimiter::hit($limiterKey, 3600);

        return $user;
    }
}
