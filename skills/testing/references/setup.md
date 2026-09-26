# Setup — Pest no Prêmio Ceará Tem Design

O `laravel new` já instala o Pest (docs/02). Este arquivo cobre o que falta para os testes
refletirem o ambiente de produção (MySQL, fuso de Fortaleza, sem serviços externos).

---

## 1. Pacotes

```bash
# Já vem com o laravel new (conferir no composer.json)
composer require --dev pestphp/pest pestphp/pest-plugin-laravel

# Testes de Livewire/Filament com a função livewire()
composer require --dev pestphp/pest-plugin-livewire

# Execução paralela (opcional)
composer require --dev brianium/paratest
```

Cobertura precisa de **PCOV** ou **Xdebug** na máquina (o Herd traz Xdebug).

---

## 2. Banco de teste: MySQL, não SQLite

O `phpunit.xml` padrão usa SQLite em memória. Para este projeto, recomenda-se **MySQL na
mesma versão do servidor** (⚠️ P65) — índices únicos, `CHECK` e comportamento de datas
precisam bater com a HostGator (docs/02).

```xml
<!-- phpunit.xml -->
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="DB_CONNECTION" value="mysql"/>
    <env name="DB_DATABASE" value="cearadesign_testing"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
</php>
```

```bash
# criar o banco de teste (Herd/local)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS cearadesign_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# ou com Sail/Docker, subir um container mysql na versão do servidor
```

> Se precisar de velocidade no dia a dia, SQLite pode ser usado localmente, mas o **CI**
> deve rodar em MySQL (ver `github-security/references/github-actions.md`).

---

## 3. `tests/Pest.php`

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Helpers de papel — usados em quase todo teste
function admin(): App\Models\User
{
    return App\Models\User::factory()->admin()->create();
}

function jurado(): App\Models\User
{
    return App\Models\User::factory()->jurado()->create();
}

function participante(): App\Models\User
{
    return App\Models\User::factory()->participante()->create();
}

function eleitor(): App\Models\User
{
    return App\Models\User::factory()->eleitor()->create();
}
```

> A sintaxe `pest()->extend()` é a do Pest 3+. Conferir a versão instalada.

---

## 4. Fuso e tempo

As regras de prazo usam `America/Fortaleza` (docs/04, P77). Garantir que:

- `config/app.php` → `'timezone' => 'America/Fortaleza'`
- Testes de prazo usam `$this->travelTo(...)` com horário explícito:

```php
$this->travelTo(Carbon::parse('2026-10-28 23:59:59', 'America/Fortaleza'));   // ainda aberto
$this->travelTo(Carbon::parse('2026-10-29 00:00:00', 'America/Fortaleza'));   // encerrado
```

---

## 5. Factories

Uma factory por model de `docs/04-modelo-dados.md`, com **states** que representam os estados
do edital. Exemplo:

```php
// database/factories/UserFactory.php
public function admin(): static        { return $this->state(['role' => Role::Admin]); }
public function jurado(): static       { return $this->state(['role' => Role::Jurado]); }
public function participante(): static { return $this->state(['role' => Role::Participante]); }
public function eleitor(): static      { return $this->state(['role' => Role::Eleitor]); }

// database/factories/EntryFactory.php
public function rascunho(): static   { return $this->state(['status' => EntryStatus::Rascunho]); }
public function enviada(): static    { return $this->state(['status' => EntryStatus::Enviada, 'submitted_at' => now()]); }
public function aprovada(): static   { return $this->state(['status' => EntryStatus::Aprovada, 'approved_at' => now()]); }
```

- Faker em português: `'faker_locale' => 'pt_BR'` em `config/app.php` (ou `fake('pt_BR')`)
- `Edition` é registro único (P32): factory com as datas do §7 e as faixas do §6 como padrão
- **Nunca** usar dados reais de participantes em factories/seeders

---

## 6. Serviços externos sempre falsos

```php
beforeEach(function () {
    Http::preventStrayRequests();   // qualquer HTTP não-fake quebra o teste
});
```

- Turnstile (P52): `Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])])`
- E-mail: `Mail::fake()` / `Notification::fake()`
- Uploads: `Storage::fake('local')` / `Storage::fake('public')`

---

## 7. Teste de arquitetura

```php
// tests/ArchTest.php
arch()->preset()->php();
arch()->preset()->security();   // proíbe eval, md5, sha1, etc.; usar ->ignoring() só com justificativa

arch('actions têm um único método público handle')
    ->expect('App\Actions')
    ->toHaveMethod('handle');

arch('sem funções de debug')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
```

---

## 8. Comandos

```bash
php artisan test                         # tudo
php artisan test --filter=SubmitEvaluation
php artisan test --parallel              # com paratest
php artisan test --coverage --min=80     # com PCOV/Xdebug
```
