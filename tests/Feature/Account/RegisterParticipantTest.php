<?php

use App\Actions\Account\RegisterParticipant;
use App\Enums\ParticipantType;
use App\Enums\PersonType;
use App\Enums\Role;
use App\Models\ParticipantProfile;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function participantInput(array $overrides = []): array
{
    return array_replace([
        'name' => 'Maria Designer',
        'email' => 'maria@example.test',
        'password' => 'senha-sintetica-1',
        'password_confirmation' => 'senha-sintetica-1',
        'person_type' => 'pf',
        'document' => '529.982.247-25',
        'phone' => '(85) 99999-0000',
        'city' => 'Fortaleza',
        'state' => 'CE',
        'participant_type' => 'profissional',
    ], $overrides);
}

function expectRegistrationError(array $overrides, string $field): void
{
    try {
        app(RegisterParticipant::class)->handle(participantInput($overrides));
        test()->fail("Esperava erro de validação em {$field}.");
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
}

describe('RegisterParticipant', function () {

    describe('✅ happy path', function () {
        it('cria usuário participante com perfil e senha com hash (P3, P4)', function () {
            $user = app(RegisterParticipant::class)->handle(participantInput());

            $profile = $user->participantProfile;
            expect($user->role)->toBe(Role::Participante)
                ->and($user->password)->not->toBe('senha-sintetica-1')
                ->and(password_verify('senha-sintetica-1', $user->password))->toBeTrue()
                ->and($user->email_verified_at)->toBeNull()
                ->and($profile->person_type)->toBe(PersonType::Pf)
                ->and($profile->document)->toBe('52998224725')
                ->and($profile->phone)->toBe('85999990000')
                ->and($profile->state)->toBe('CE')
                ->and($profile->participant_type)->toBe(ParticipantType::Profissional);
        });

        it('cria pessoa jurídica com CNPJ numérico ou alfanumérico (P3, P81)', function (string $cnpj, string $stored) {
            $user = app(RegisterParticipant::class)->handle(participantInput([
                'person_type' => 'pj',
                'document' => $cnpj,
                'participant_type' => 'empresa',
            ]));

            expect($user->participantProfile->document)->toBe($stored);
        })->with([
            'numérico' => ['11.222.333/0001-81', '11222333000181'],
            'alfanumérico' => ['12.abc.345/01de-35', '12ABC34501DE35'],
        ]);

        it('aceita todos os tipos de participante do §2', function (ParticipantType $type) {
            $user = app(RegisterParticipant::class)->handle(participantInput(['participant_type' => $type->value]));

            expect($user->participantProfile->participant_type)->toBe($type);
        })->with(ParticipantType::cases());
    });

    describe('⚠️ edge cases', function () {
        it('ignora role enviado na entrada', function () {
            $user = app(RegisterParticipant::class)->handle(participantInput(['role' => 'admin']));

            expect($user->role)->toBe(Role::Participante);
        });

        it('normaliza e-mail e UF', function () {
            $user = app(RegisterParticipant::class)->handle(participantInput([
                'email' => '  Maria@Example.TEST ',
                'state' => 'ce',
            ]));

            expect($user->email)->toBe('maria@example.test')
                ->and($user->participantProfile->state)->toBe('CE');
        });

        it('aceita telefone fixo com 10 dígitos', function () {
            $user = app(RegisterParticipant::class)->handle(participantInput(['phone' => '(85) 3333-0000']));

            expect($user->participantProfile->phone)->toBe('8533330000');
        });
    });

    describe('❌ cenários de erro', function () {
        it('rejeita documento já usado, com ou sem máscara (P74)', function () {
            app(RegisterParticipant::class)->handle(participantInput());

            expectRegistrationError(['email' => 'outra@example.test', 'document' => '52998224725'], 'document');
            expect(User::count())->toBe(1);
        });

        it('rejeita e-mail já usado', function () {
            app(RegisterParticipant::class)->handle(participantInput());

            expectRegistrationError(['document' => '111.444.777-35'], 'email');
        });

        it('valida o documento conforme o tipo de pessoa', function (array $overrides) {
            expectRegistrationError($overrides, 'document');
        })->with([
            'PF com CPF inválido' => [['document' => '529.982.247-24']],
            'PF com CNPJ' => [['document' => '11.222.333/0001-81']],
            'PJ com CPF' => [['person_type' => 'pj', 'document' => '529.982.247-25']],
            'PJ com CNPJ inválido' => [['person_type' => 'pj', 'document' => '11.222.333/0001-80']],
        ]);

        it('rejeita campos fora das listas ou do formato', function (array $overrides, string $field) {
            expectRegistrationError($overrides, $field);
        })->with([
            'tipo de pessoa inexistente' => [['person_type' => 'mei'], 'person_type'],
            'tipo de participante fora do §2' => [['participant_type' => 'patrocinador'], 'participant_type'],
            'UF inexistente' => [['state' => 'XX'], 'state'],
            'telefone sem DDD' => [['phone' => '99999-0000'], 'phone'],
            'senha curta' => [['password' => 'curta', 'password_confirmation' => 'curta'], 'password'],
            'confirmação divergente' => [['password_confirmation' => 'outra-senha-1'], 'password'],
            'e-mail inválido' => [['email' => 'nao-e-email'], 'email'],
            'cidade vazia' => [['city' => '   '], 'city'],
        ]);

        it('em corrida pelo mesmo documento, desfaz o usuário e devolve erro de campo (P74)', function () {
            // Simula outra requisição gravando o mesmo documento depois da validação,
            // dentro da transação: o índice único barra e nada fica pela metade.
            User::created(function (User $created) {
                if ($created->email === 'maria@example.test') {
                    ParticipantProfile::factory()->create(['document' => '52998224725']);
                }
            });

            expectRegistrationError([], 'document');
            expect(User::where('email', 'maria@example.test')->exists())->toBeFalse()
                ->and(ParticipantProfile::where('document', '52998224725')->count())->toBe(0);
        });
    });

});
