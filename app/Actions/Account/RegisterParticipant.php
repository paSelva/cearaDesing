<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\ParticipantType;
use App\Enums\PersonType;
use App\Enums\Role;
use App\Models\User;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro público do participante (docs/07, SPEC-002).
 *
 * Cria `users` com papel participante + `participant_profiles` (P3, P4, P74).
 * Sem confirmação de e-mail (P73). O papel nunca vem da entrada.
 */
final class RegisterParticipant
{
    public const UFS = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA',
        'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    /** @param array<string, mixed> $input */
    public function handle(array $input): User
    {
        $input = $this->normalize($input);

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'person_type' => ['required', Rule::enum(PersonType::class)],
            'document' => [
                'required',
                'string',
                $input['person_type'] === PersonType::Pj->value ? new Cnpj : new Cpf,
                Rule::unique('participant_profiles', 'document'),
            ],
            'phone' => ['required', 'string', 'regex:/^\d{10,11}$/'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(self::UFS)],
            'participant_type' => ['required', Rule::enum(ParticipantType::class)],
        ], [
            'document.unique' => 'Já existe uma conta de participante com este documento.',
            'phone.regex' => 'Informe o telefone com DDD, só números (10 ou 11 dígitos).',
        ])->validate();

        try {
            return DB::transaction(function () use ($validated): User {
                $user = new User([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                ]);
                $user->role = Role::Participante;
                $user->save();

                $user->participantProfile()->create([
                    'person_type' => $validated['person_type'],
                    'document' => $validated['document'],
                    'phone' => $validated['phone'],
                    'city' => $validated['city'],
                    'state' => $validated['state'],
                    'participant_type' => $validated['participant_type'],
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Duas requisições simultâneas passam pela validação; o índice único decide (P74).
            throw ValidationException::withMessages([
                'document' => 'Já existe uma conta com este e-mail ou documento.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        $personType = is_string($input['person_type'] ?? null) ? $input['person_type'] : '';
        $document = is_string($input['document'] ?? null) ? $input['document'] : '';

        $input['person_type'] = $personType;
        $input['document'] = $personType === PersonType::Pj->value
            ? Cnpj::normalize($document)
            : Cpf::normalize($document);
        $input['phone'] = is_string($input['phone'] ?? null)
            ? (preg_replace('/\D/', '', $input['phone']) ?? '')
            : '';
        $input['state'] = is_string($input['state'] ?? null) ? strtoupper(trim($input['state'])) : '';
        $input['email'] = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        $input['city'] = is_string($input['city'] ?? null) ? trim($input['city']) : '';

        return $input;
    }
}
