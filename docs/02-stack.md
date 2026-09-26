# 02 — Stack e tecnologias

**Definido pelo responsável do projeto:** PHP + Laravel · PHP 8.4 (T3) · MySQL (P33) · hospedagem compartilhada HostGator (P30, P63) · auth do participante e do eleitor via Fortify (T1).
O resto deste documento é **recomendação**. As versões e a compatibilidade entre elas foram conferidas no Packagist e nas release notes em **26/09/2026**.

## Núcleo

| Item | Versão | Situação | Observação |
|---|---|---|---|
| **PHP** | 8.4 | Definido (T3) | A máquina local tem **8.3.33** e precisa ser atualizada. A hospedagem também precisa ter 8.4. |
| **Laravel** | 13.x | Definido (Laravel) / versão recomendada | Lançado em mar/2026. Bug fixes até o 3º tri de 2027 e segurança até o 1º tri de 2028 (fonte: Laravel News). |
| **Filament** | 5.x (atual: 5.9.0) | Recomendação | Painéis de **admin e jurado** (T1). `filament/support` 5.9.0 aceita `illuminate/contracts ^11.28\|^12.0\|^13.0`. |
| **Livewire** | 4.x | Dependência do Filament 5 | `filament/support` exige `livewire/livewire ^4.4.2`. |
| **Tailwind CSS** | 4.x | Recomendação | Padrão atual do Laravel e do Filament. |
| **Banco** | MySQL | Definido (P33, P64) | O Laravel 13 aceita MySQL 5.7 ou superior (docs oficiais). A HostGator compartilhada oferece 5.7 ou 8.0 (⚠️ P65), e a máquina local tem 9.6.0. Recomendação: desenvolver com a **mesma versão do servidor** (ex.: via Docker/Sail) para evitar diferenças. |
| **Testes** | Pest | Recomendação | Versão instalada pelo `laravel new`. |

## Pacotes candidatos (compatibilidade com Laravel 13 conferida)

| Pacote | Versão atual | Compatível | Uso proposto | Depende de |
|---|---|---|---|---|
| `spatie/laravel-medialibrary` | 11.23.8 | L13 ✅ | Imagens das inscrições | P6 |
| `filament/spatie-laravel-media-library-plugin` | 5.9.0 | Filament 5 ✅ | Exibir e gerenciar as imagens nos painéis admin e jurado (o upload do participante fica nas páginas próprias, T1) | P6 |
| `spatie/laravel-activitylog` | 5.1.1 | L13 ✅ · **PHP ^8.4** | Histórico de alterações (notas, status) | P46 · T3 ✅ |
| `laravel/fortify` | 1.40.0 | L13 ✅ | Cadastro, login, verificação e reset de senha do **participante e do eleitor**, em páginas próprias | T1 ✅ |

Papéis: um campo `role` em `users` + Policies do Laravel, sem pacote extra (T2).

Serviços externos confirmados:
- **Cloudflare Turnstile**: captcha no cadastro do eleitor (P52). O pacote de integração ainda precisa ter a versão verificada.

Pacotes que só entram se a funcionalidade for confirmada:
- exportação para planilha (ainda não perguntada).

## Ambiente de desenvolvimento

- Composer **não está instalado** na máquina.
- Docker está instalado, portanto Laravel Sail é uma opção.
- Node **24.14.1** está instalado.
- **Recomendação:** instalar o **Laravel Herd** (traz PHP, Composer e o instalador do Laravel) ou `brew install composer`.

## Produção

- **Domínio:** já existe, e o sistema ficará em um **subdomínio** (P29). ⚠️ Nome exato: P62.
- **Hospedagem:** **HostGator, plano compartilhado**, servidor já existente (P30, P63). **Sem SSH** (P66).
- **Manutenção:** o sistema é só para a edição 2026 (P32).
- **E-mail transacional:** 🟡 aguardando a organização (P31).

### O que a HostGator suporta (documentação oficial, consultada em 26/09/2026)

| Recurso | Compartilhada | VPS / Dedicado |
|---|---|---|
| PHP 8.4 | ✅ | ✅ |
| MySQL | 5.7 (CentOS) ou 8.0 (AlmaLinux) | 8.0 |
| **PostgreSQL** | ❌ | ✅ |
| SSH | A tabela de compatibilidade marca como não incluso. O artigo de instalação do Laravel diz que o SSH precisa estar "liberado no plano" | ✅ incluso |
| Cron | ✅ | ✅ |
| Composer, Redis, Supervisor | Não mencionados na documentação | Não mencionados na documentação |

**Resolvido:** como o plano é compartilhado e não tem PostgreSQL, o banco será **MySQL** (P64). ⚠️ Versão: P65.

### Deploy sem SSH (Recomendação, P66)

Como o plano não tem SSH, não dá para rodar Composer nem `php artisan` no servidor. Proposta:
1. **Build local:** `composer install --no-dev --optimize-autoloader` e `npm run build`, gerando um pacote com `vendor/` e os assets compilados.
2. **Envio** por FTP ou pelo Gerenciador de Arquivos do cPanel (upload do .zip e extração).
3. **Migrations e comandos artisan:** rodar por um **cron job temporário** no cPanel. O cron é suportado no plano compartilhado, segundo a tabela da HostGator.
4. **Raiz do subdomínio** apontando para `public/`. ⚠️ Confirmar no cPanel (P68).
5. O SSH **não será solicitado** (P67), então esse é o processo definitivo.

Consequências para o código (Recomendação):
- sem Redis garantido, filas, cache e sessão usam o driver `database`;
- sem worker permanente garantido, as filas rodam pelo **cron** (`schedule:run` + `queue:work --stop-when-empty`);
- o armazenamento de arquivos fica no disco do próprio servidor.

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
