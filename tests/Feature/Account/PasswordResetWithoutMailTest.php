<?php

/*
 | Sem provedor de e-mail (MAIL_MAILER=log), a recuperação de senha prometeria um link que
 | nunca chega: as rotas saem do ar e o login mostra o contato da organização.
 */

function withMailer(string $mailer): void
{
    putenv("MAIL_MAILER={$mailer}");
    $_ENV['MAIL_MAILER'] = $_SERVER['MAIL_MAILER'] = $mailer;
    test()->refreshApplication();
}

afterEach(fn () => withMailer('array'));

it('com provedor de e-mail, o login oferece a recuperação de senha', function () {
    $this->get('/login')->assertOk()->assertSee('Esqueci minha senha')->assertDontSee('Fale com a organização');
    $this->get('/forgot-password')->assertOk();
});

it('sem provedor de e-mail, a recuperação sai do ar e o login mostra o contato da organização', function () {
    withMailer('log');

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('Esqueci minha senha')
        ->assertSee('Fale com a organização pelo Instagram')
        ->assertSee('https://www.instagram.com/cearatemdesign', false);
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password', ['email' => 'alguem@example.test'])->assertNotFound();
    $this->get('/reset-password/token-qualquer')->assertNotFound();
});
