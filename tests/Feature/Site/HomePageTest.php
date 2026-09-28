<?php

use App\Models\Category;
use App\Models\Criterion;
use Illuminate\Support\Carbon;

describe('site público (P26)', function () {
    it('mostra o conteúdo do edital com categorias, critérios, faixas e datas do banco', function () {
        $edition = openEdition();
        $category = Category::factory()->for($edition)->create(['code' => '3.1', 'name' => 'Design Gráfico e Comunicação Visual', 'description' => 'Identidades visuais e marcas.']);
        Criterion::factory()->for($category)->create(['name' => 'Conceito e criatividade', 'sort' => 1]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Prêmio<br>Ceará tem<br>design', false)
            ->assertSee('A Associação Ceará Design realiza a primeira edição')
            ->assertSee('Design Gráfico e Comunicação Visual')
            ->assertSee('Identidades visuais e marcas.')
            ->assertSee('Qualidade da ideia, da abordagem e das soluções apresentadas.')
            ->assertSee('54 a 60 pontos')
            ->assertSee('36 a 41 pontos')
            ->assertSee('28 de setembro a 28 de outubro')
            ->assertSee('29 de outubro a 20 de novembro')
            ->assertSee('A inscrição não transfere para a Associação Ceará Design os direitos autorais do projeto.');
    });

    it('faixas e máximo acompanham a configuração da edição', function () {
        $edition = openEdition();
        $edition->forceFill(['band_gold_min' => 55])->save();

        $this->get('/')->assertSee('55 a 60 pontos')->assertSee('48 a 54 pontos');
    });

    it('botão de inscrição só aparece com as inscrições abertas', function () {
        openEdition();
        $this->get('/')->assertSee('Inscrever projeto');

        $this->travelTo(Carbon::parse('2026-10-29 00:00:00', 'America/Fortaleza'));
        $this->get('/')->assertDontSee('site-button--grande', false);
    });

    it('não mostra parceiros, diretoria e avaliadores enquanto não houver conteúdo (P27, P59)', function () {
        openEdition();

        $this->get('/')
            ->assertDontSee('id="parceiros"', false)
            ->assertDontSee('id="avaliadores"', false);
    });

    it('funciona mesmo sem edição cadastrada', function () {
        $this->get('/')->assertOk()->assertSee('Sobre o prêmio');
    });
});
