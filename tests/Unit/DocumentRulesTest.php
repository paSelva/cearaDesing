<?php

use App\Rules\Cnpj;
use App\Rules\Cpf;

describe('Cpf (dígito verificador, P4/P42)', function () {
    it('aceita CPF válido com e sem máscara', function (string $cpf) {
        expect(Cpf::isValid($cpf))->toBeTrue();
    })->with(['529.982.247-25', '52998224725', '111.444.777-35']);

    it('rejeita CPF inválido', function (string $cpf) {
        expect(Cpf::isValid($cpf))->toBeFalse();
    })->with([
        'dígito errado' => '529.982.247-24',
        'sequência repetida' => '111.111.111-11',
        'curto' => '5299822472',
        'longo' => '529982247250',
        'vazio' => '',
        'letras' => 'abc.def.ghi-jk',
    ]);

    it('normaliza removendo a máscara', function () {
        expect(Cpf::normalize('529.982.247-25'))->toBe('52998224725');
    });
});

describe('Cnpj (numérico e alfanumérico, P4/P81)', function () {
    it('aceita CNPJ válido', function (string $cnpj) {
        expect(Cnpj::isValid($cnpj))->toBeTrue();
    })->with([
        'numérico com máscara' => '11.222.333/0001-81',
        'numérico sem máscara' => '11222333000181',
        'alfanumérico (exemplo oficial da Receita)' => '12.ABC.345/01DE-35',
        'alfanumérico em minúsculas' => '12abc34501de35',
    ]);

    it('rejeita CNPJ inválido', function (string $cnpj) {
        expect(Cnpj::isValid($cnpj))->toBeFalse();
    })->with([
        'dígito errado' => '11.222.333/0001-80',
        'alfanumérico com dígito errado' => '12.ABC.345/01DE-34',
        'sequência repetida' => '00.000.000/0000-00',
        'verificador com letra' => '12ABC34501DEA5',
        'curto' => '1122233300018',
        'CPF no lugar de CNPJ' => '52998224725',
        'vazio' => '',
    ]);

    it('normaliza removendo a máscara e mantendo letras em maiúsculas', function () {
        expect(Cnpj::normalize('12.abc.345/01de-35'))->toBe('12ABC34501DE35');
    });
});
