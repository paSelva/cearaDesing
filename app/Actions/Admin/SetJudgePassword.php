<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** O admin define uma nova senha para o jurado (P82). A organização repassa a senha (P17). */
final class SetJudgePassword
{
    /** @param array<string, mixed> $input */
    public function handle(User $judge, array $input): User
    {
        if ($judge->role !== Role::Jurado) {
            throw ValidationException::withMessages([
                'password' => 'Só é possível alterar a senha de jurados por aqui.',
            ]);
        }

        $validated = Validator::make($input, [
            'password' => ['required', 'string', Password::min(12), 'confirmed'],
        ])->validate();

        // Nova senha encerra sessões "lembradas" do jurado.
        $judge->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return $judge;
    }
}
