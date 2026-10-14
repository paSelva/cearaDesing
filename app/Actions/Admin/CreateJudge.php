<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * O admin cria a conta do jurado com senha (P17).
 * Mini bio e foto (P59) entram com a seção Avaliadores do site (depende da Media Library).
 */
final class CreateJudge
{
    /** @param array<string, mixed> $input */
    public function handle(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(12), 'confirmed'],
        ])->validate();

        $judge = new User([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
        ]);
        $judge->role = Role::Jurado;
        $judge->save();

        return $judge;
    }
}
