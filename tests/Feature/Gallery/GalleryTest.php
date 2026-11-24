<?php

use App\Models\Edition;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function publishedEdition(): Edition
{
    $edition = openEdition();
    $edition->forceFill(['results_published_at' => now()])->save();

    return $edition;
}

describe('galeria (P24, P55, P57)', function () {
    it('fica fechada até a publicação do resultado, sem projetos nem imagens', function () {
        $edition = openEdition();
        $entry = entryFor(participant(), regularCategory($edition), 'aprovada');
        $image = $entry->addMedia(fakeImage())->toMediaCollection('images');

        $this->get('/galeria')->assertOk()->assertSee('A galeria abre junto com a divulgação do resultado')->assertDontSee($entry->title);
        $this->get("/galeria/{$entry->id}")->assertNotFound();
        $this->get("/galeria/{$entry->id}/imagens/{$image->id}")->assertNotFound();
        $this->get('/')->assertDontSee(route('gallery.index'));

        $edition->forceFill(['results_published_at' => now()])->save();
        $this->get('/galeria')->assertSee($entry->title);
        $this->get("/galeria/{$entry->id}/imagens/{$image->id}")->assertOk();
        $this->get('/')->assertSee(route('gallery.index'));
    });

    it('mostra só aprovadas com nome, categoria e imagens; nada de autoria, textos ou dados pessoais', function () {
        $category = regularCategory(publishedEdition());
        $approved = entryFor(participant(), $category, 'aprovada');
        $approved->forceFill(['title' => 'Projeto aprovado', 'authorship_credits' => 'Autoria secreta XYZ', 'summary' => 'Resumo secreto XYZ'])->save();
        $approved->links()->create(['url' => 'https://example.test/secreto', 'sort' => 1]);
        $sent = entryFor(participant(), $category, 'enviada');
        $sent->forceFill(['title' => 'Projeto só enviado'])->save();
        $gone = entryFor(participant(), $category, 'desclassificada');
        $gone->forceFill(['title' => 'Projeto desclassificado'])->save();

        $this->get('/galeria')->assertOk()->assertSee($approved->title)->assertDontSee($sent->title)->assertDontSee($gone->title);
        $this->get("/galeria/{$approved->id}")
            ->assertOk()
            ->assertSee($approved->title)
            ->assertSee($category->name)
            ->assertDontSee('Autoria secreta XYZ')
            ->assertDontSee('Resumo secreto XYZ')
            ->assertDontSee('https://example.test/secreto')
            ->assertDontSee($approved->user->email);
        $this->get("/galeria/{$sent->id}")->assertNotFound();
        $this->get("/galeria/{$gone->id}")->assertNotFound();
    });

    it('imagem pública só da coleção de imagens; PDF complementar dá 404', function () {
        $entry = entryFor(participant(), regularCategory(publishedEdition()), 'aprovada');
        $image = $entry->addMedia(fakeImage())->toMediaCollection('images');
        $pdf = $entry->addMedia(fakePdf())->toMediaCollection('supplementary_pdf');

        $this->get("/galeria/{$entry->id}/imagens/{$image->id}")->assertOk()->assertHeader('Cache-Control', 'max-age=300, public');
        $this->get("/galeria/{$entry->id}/imagens/{$pdf->id}")->assertNotFound();
    });

    it('mostra distinção e nota final', function () {
        $entry = entryFor(participant(), regularCategory(publishedEdition()), 'aprovada');
        $entry->forceFill(['final_score' => 55, 'distinction' => 'ouro'])->save();

        $this->get("/galeria/{$entry->id}")->assertSee('Ouro, 55 pontos');
    });
});

it('não há voto popular: rotas e telas de eleitor não existem (P1)', function (string $method, string $path) {
    $this->call($method, $path)->assertNotFound();
})->with([
    ['GET', '/eleitor'],
    ['GET', '/eleitor/cadastro'],
    ['POST', '/eleitor/cadastro'],
    ['POST', '/galeria/1/votar'],
    ['GET', '/admin/voto-popular'],
]);
