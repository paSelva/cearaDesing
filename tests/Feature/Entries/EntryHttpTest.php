<?php

use App\Actions\Entries\AddEntryImage;
use App\Enums\EntryStatus;
use App\Enums\ParticipantType;
use App\Enums\Role;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

describe('fluxo pelo site', function () {
    it('participante cria, preenche, envia imagem e envia a inscrição', function () {
        $category = regularCategory(openEdition());
        $user = participant();
        $this->actingAs($user);

        $this->get('/participante')->assertOk()->assertSee($category->name)->assertSee('Inscrever projeto');

        $this->post('/participante/inscricoes', ['category_id' => $category->id])->assertRedirect();
        $entry = Entry::sole();

        $this->post("/participante/inscricoes/{$entry->id}/imagens", ['image' => fakeImage()])->assertSessionHasNoErrors();
        $this->put("/participante/inscricoes/{$entry->id}", completeTexts(['intent' => 'submit', 'consents' => allConsents()]))
            ->assertSessionHasNoErrors()
            ->assertRedirect("/participante/inscricoes/{$entry->id}");

        expect($entry->fresh()->status)->toBe(EntryStatus::Enviada)
            ->and($entry->consents()->first()->ip)->toBe('127.0.0.1');

        $this->get("/participante/inscricoes/{$entry->id}")
            ->assertOk()
            ->assertSee('Enviada')
            ->assertSee('Cadeira Jangada');
    });

    it('se o envio falha, os textos digitados ficam salvos e a inscrição continua rascunho', function () {
        $user = participant();
        $entry = entryFor($user, regularCategory(openEdition()));

        $this->actingAs($user)
            ->from("/participante/inscricoes/{$entry->id}")
            ->put("/participante/inscricoes/{$entry->id}", completeTexts(['intent' => 'submit', 'consents' => allConsents()]))
            ->assertRedirect("/participante/inscricoes/{$entry->id}")
            ->assertSessionHasErrors('entry');

        $entry->refresh();
        expect($entry->status)->toBe(EntryStatus::Rascunho)
            ->and($entry->title)->toBe('Cadeira Jangada')
            ->and($entry->summary)->toBe('Uma cadeira inspirada nas jangadas.');
    });

    it('salvar rascunho com intent=save não envia', function () {
        $user = participant();
        $entry = entryFor($user, regularCategory(openEdition()), 'completo');
        app(AddEntryImage::class)->handle($entry, fakeImage());

        $this->actingAs($user)->put("/participante/inscricoes/{$entry->id}", completeTexts(['intent' => 'save', 'consents' => allConsents()]));

        expect($entry->fresh()->status)->toBe(EntryStatus::Rascunho);
    });

    it('mostra os textos do §8 e o que falta para enviar', function () {
        $user = participant();
        $entry = entryFor($user, regularCategory(openEdition()));

        $this->actingAs($user)->get("/participante/inscricoes/{$entry->id}")
            ->assertOk()
            ->assertSee('Confirmo que as informações apresentadas são verdadeiras.')
            ->assertSee('Envie pelo menos 1 imagem do projeto.');
    });

    it('esconde categorias pela regra de estudantes (P9)', function () {
        $edition = openEdition();
        $regular = regularCategory($edition);
        $student = studentCategory($edition);

        $this->actingAs(participant(ParticipantType::Estudante))->get('/participante')
            ->assertSee($student->name)
            ->assertDontSee($regular->name);
    });
});

describe('upload acima do limite do PHP', function () {
    it('volta para a página com mensagem em vez de erro', function () {
        $user = participant();
        $entry = entryFor($user, regularCategory(openEdition()));

        $this->actingAs($user)
            ->from("/participante/inscricoes/{$entry->id}")
            ->call('POST', "/participante/inscricoes/{$entry->id}/pdf", [], [], [], [
                'CONTENT_LENGTH' => (string) (64 * 1024 * 1024),
            ])
            ->assertRedirect("/participante/inscricoes/{$entry->id}");
        expect(session('errors')->first('upload'))
            ->toBe('O arquivo é maior que o limite permitido. Imagens: até 5 MB; PDF: até 10 MB.');
    });
});

describe('acesso (IDOR)', function () {
    it('outro participante não vê, não edita, não envia arquivo nem baixa arquivo', function () {
        $edition = openEdition();
        $owner = participant();
        $entry = entryFor($owner, regularCategory($edition));
        $media = app(AddEntryImage::class)->handle($entry, fakeImage());

        $this->actingAs(participant());

        $this->get("/participante/inscricoes/{$entry->id}")->assertForbidden();
        $this->put("/participante/inscricoes/{$entry->id}", completeTexts())->assertForbidden();
        $this->post("/participante/inscricoes/{$entry->id}/imagens", ['image' => fakeImage()])->assertForbidden();
        $this->delete("/participante/inscricoes/{$entry->id}/imagens/{$media->id}")->assertForbidden();
        $this->post("/participante/inscricoes/{$entry->id}/pdf", ['pdf' => fakePdf()])->assertForbidden();
        $this->delete("/participante/inscricoes/{$entry->id}/pdf")->assertForbidden();
        $this->get("/participante/inscricoes/{$entry->id}/arquivos/{$media->id}")->assertForbidden();

        expect($entry->fresh()->getMedia('images'))->toHaveCount(1);
    });

    it('dono baixa o próprio arquivo; arquivo de outra inscrição dá 404', function () {
        $edition = openEdition();
        $owner = participant();
        $mine = entryFor($owner, regularCategory($edition));
        $other = entryFor(participant(), regularCategory($edition));
        $myImage = app(AddEntryImage::class)->handle($mine, fakeImage());
        $foreign = app(AddEntryImage::class)->handle($other, fakeImage());

        $this->actingAs($owner);
        $this->get("/participante/inscricoes/{$mine->id}/arquivos/{$myImage->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get("/participante/inscricoes/{$mine->id}/arquivos/{$foreign->id}")->assertNotFound();
    });

    it('arquivos não são servidos pela pasta pública', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()));
        $media = app(AddEntryImage::class)->handle($entry, fakeImage());

        // Disco local privado: sem URL assinada o Laravel responde 403 (mesmo padrão da SPEC-000).
        $this->get("/storage/{$media->id}/{$media->file_name}")->assertForbidden();
    });

    it('outros papéis não acessam a área de inscrições', function (Role $role) {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get("/participante/inscricoes/{$entry->id}")
            ->assertForbidden();
    })->with([Role::Admin, Role::Jurado, Role::Eleitor]);
});
