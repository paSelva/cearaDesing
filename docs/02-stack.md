# 02 — Stack e tecnologias

**Definido pelo responsável do projeto:** PHP + Laravel · PHP 8.4 (T3) · MySQL (P33) ·
hospedagem administrada por cPanel · auth do participante e do eleitor via Fortify (T1).
O resto deste documento é **recomendação**. As versões e a compatibilidade entre elas foram conferidas no Packagist e nas release notes em **26/09/2026**.

## Núcleo

| Item | Versão | Situação | Observação |
|---|---|---|---|
| **PHP** | ≥ 8.4.1 | Travado pelas dependências | `composer.lock` inclui componentes Symfony que exigem 8.4.1+. PHP Web e CLI/Cron precisam ser conferidos separadamente. |
| **Laravel** | 13.x | Definido (Laravel) / versão recomendada | Lançado em mar/2026. Bug fixes até o 3º tri de 2027 e segurança até o 1º tri de 2028 (fonte: Laravel News). |
| **Filament** | 5.x (atual: 5.9.0) | Recomendação | Painéis de **admin e jurado** (T1). `filament/support` 5.9.0 aceita `illuminate/contracts ^11.28\|^12.0\|^13.0`. |
| **Livewire** | 4.x | Dependência do Filament 5 | `filament/support` exige `livewire/livewire ^4.4.2`. |
| **Tailwind CSS** | 4.x | Recomendação | Padrão atual do Laravel e do Filament. |
| **Banco** | Percona Server 5.7.44-48 (produção) e MySQL 8 | Definido e validado localmente | O cPanel informou Percona Server 5.7.44-48. Migrations, seeder e testes relevantes passaram na imagem oficial `percona:5.7.44`; MySQL 8.0.46 também tem histórico de validação. MariaDB não foi validado. |
| **Testes** | Pest | Recomendação | Versão instalada pelo `laravel new`. |

## Pacotes candidatos (compatibilidade com Laravel 13 conferida)

| Pacote | Versão atual | Compatível | Uso proposto | Depende de |
|---|---|---|---|---|
| `spatie/laravel-medialibrary` | 11.23.8 | L13 ✅ | Imagens e PDF das inscrições — **instalado na SPEC-003**; exige `ext-exif` (adicionada às imagens Docker e ao CI). Sem conversões de imagem (não depende de GD/Imagick) | P6 ✅ |
| `filament/spatie-laravel-media-library-plugin` | 5.9.0 | Filament 5 ✅ | Exibir e gerenciar as imagens nos painéis admin e jurado (o upload do participante fica nas páginas próprias, T1) | P6 |
| `spatie/laravel-activitylog` | 5.1.1 | L13 ✅ · **PHP ^8.4** | Histórico de alterações (notas, status) — **instalado na SPEC-004** (log `triagem`); sem extensão adicional | P46 · T3 ✅ |
| `laravel/fortify` | 1.40.0 | L13 ✅ | Cadastro, login, verificação e reset de senha do **participante e do eleitor**, em páginas próprias | T1 ✅ |

Papéis: um campo `role` em `users` + Policies do Laravel, sem pacote extra (T2).

Serviços externos confirmados:
- **Cloudflare Turnstile**: implementado no cadastro do eleitor (P52), com validação backend via HTTPS. As chaves e a conectividade de saída do servidor ainda precisam de teste.

Pacotes que só entram se a funcionalidade for confirmada:
- exportação para planilha (ainda não perguntada).

## Ambiente de desenvolvimento

- Composer **não está instalado** na máquina.
- Docker está instalado, portanto Laravel Sail é uma opção.
- Node **24.14.1** está instalado.
- **Recomendação:** instalar o **Laravel Herd** (traz PHP, Composer e o instalador do Laravel) ou `brew install composer`.

## Produção

- **Domínio:** o valor final ainda deve ser confirmado e usado em `APP_URL`/Turnstile. Passo a passo: [deploy-hostgator.md](runbooks/deploy-hostgator.md).
- **Hospedagem:** conta cPanel na HostGator (`chega101`, pasta pessoal `/home1/chega101`), **sem SSH** (P66). Deploy pelo Git Version Control do cPanel; Document Root ainda precisa de confirmação (P68).
- **Manutenção:** o sistema é só para a edição 2026 (P32).
- **E-mail transacional:** 🟡 aguardando a organização (P31).

### O que precisa ser confirmado na conta real

Não usar a documentação genérica do fornecedor como prova do servidor contratado. Confirmar
no cPanel: PHP Web e CLI ≥ 8.4.1, extensões do lockfile, Percona Server 5.7.44-48/InnoDB, Cron, Terminal/SSH,
Document Root terminando em `/public`, HTTPS, SMTP, limites e quotas. P65 foi respondida;
os demais itens ainda precisam de teste na conta real.

### Deploy pelo Git Version Control do cPanel (principal) ou Cron (alternativa)

Nenhum caminho roda Composer/npm no servidor (a HostGator compartilhada não oferece Node.js):
1. **Build no GitHub Actions** (`release-cpanel.yml` → `scripts/build-release.sh SHA`), disparado manualmente na `main` com o SHA aprovado.
2. **Branch `producao`:** o pacote (com `vendor/` e `public/build/`) vira um commit, junto de um `.cpanel.yml`. Só avança, nunca force-push.
3. **No cPanel:** Update from Remote → Deploy HEAD Commit roda `deploy/install-release.sh install-dir`, que copia para `~/ceara-design/releases/SHA`, roda `deploy/cron-deploy.sh` (preflight, migrations, seeder) e troca o link `current` só após sucesso.
4. **Raiz do subdomínio** em `/home1/chega101/ceara-design/current/public`. ⚠️ Confirmar no cPanel (P68).
5. **Alternativa:** upload do ZIP pelo Gerenciador de Arquivos e o mesmo `cron-deploy.sh` por um **Cron temporário**. O workflow `deploy.yml` (SSH) fica dormente.

Passo a passo: [deploy-hostgator.md](runbooks/deploy-hostgator.md).

Limites de PHP exigidos pela SPEC-003 (conferir no MultiPHP INI Editor do cPanel, ⚠️ não verificado): `upload_max_filesize` ≥ 10M, `post_max_size` ≥ 12M, `display_errors = Off`. O upload é feito **um arquivo por requisição** para caber nesses limites. Extensão `exif` necessária (Media Library).

Consequências para a release atual:
- cache e sessão usam banco; `QUEUE_CONNECTION=sync`;
- não há jobs/listeners enfileirados nem tarefas agendadas, portanto não há worker/Cron permanente;
- uploads ficam em `storage/app/private` e são entregues por rotas autorizadas; não há `storage:link`.

Fontes:
- [Compatibilidades da HostGator](https://suporte.hostgator.com.br/hc/pt-br/articles/30811116692115-Quais-s%C3%A3o-as-compatibilidades-da-HostGator)
- [Versões dos softwares da HostGator](https://suporte.hostgator.com.br/hc/pt-br/articles/30820193707155-Vers%C3%B5es-dos-principais-softwares-da-HostGator)
- [Laravel 13 – bancos suportados](https://laravel.com/docs/13.x/database)
- [Como instalar o Laravel na HostGator](https://suporte.hostgator.com.br/hc/pt-br/articles/30814116483987-Como-instalar-o-Laravel)

## Decisões de arquitetura (Recomendação)

- **Sem SPA** (React/Vue + API separada) e **sem Inertia**: o Filament cobre admin e jurado, e o site, a área do participante e o voto popular ficam em Blade + Livewire. Fica uma aplicação só para manter.
- **Sem microsserviços**: é um único sistema para um evento.
- **Auth do participante e do eleitor:** páginas próprias com Fortify, na identidade visual do site. Filament fica só para admin e jurado (T1, definido).

## Fontes

- [Laravel 13 – Release notes](https://laravel.com/docs/13.x/releases)
- [Laravel News – Laravel 13](https://laravel-news.com/laravel-13-released)
- [Laravel News – Filament 5](https://laravel-news.com/filament-5)
- [Filament 5 – Upgrade guide](https://filamentphp.com/docs/5.x/upgrade-guide)
- Packagist (`repo.packagist.org/p2/<pacote>.json`), consultado em 26/09/2026
