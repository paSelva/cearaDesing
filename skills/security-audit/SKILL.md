---
name: security-audit
description: >
  Realiza auditoria de segurança completa no sistema do Prêmio Ceará Tem Design
  (Laravel 13, PHP 8.4, MySQL, Fortify, Filament 5, Livewire 4, Blade/Tailwind,
  hospedagem compartilhada HostGator sem SSH). Identifica vulnerabilidades reais no código,
  aponta pontos de invasão e gera um relatório priorizado com severidade e correções prontas
  para aplicar. Use este skill sempre que o usuário mencionar: "checar segurança", "auditar
  o projeto", "vulnerabilidades", "pontos de invasão", "security review", "revisar autenticação",
  "hardening", "OWASP", "XSS", "SQL injection", "CSRF", "mass assignment", "Policy",
  "painel Filament exposto", "fraude no voto popular", "LGPD", "APP_DEBUG", ".env exposto",
  "deploy na HostGator", ou qualquer variação. Acione também quando o usuário disser
  "está seguro?", "o que pode ser atacado?", "antes de ir pra produção", "revisão de segurança"
  ou "preciso de um pentest simples".
---

# Security Audit — Prêmio Ceará Tem Design (Laravel)

Orquestra uma auditoria completa de segurança cobrindo as camadas do sistema.
Ao final, entrega um relatório consolidado com achados por severidade (CRÍTICO / ALTO / MÉDIO / BAIXO).

> **Skills especializadas disponíveis:**
> Este skill é o orquestrador. Para auditorias focadas em uma camada, use
> `security-frontend` (Blade, Livewire, Filament, assets), `security-backend`
> (rotas, Fortify, Policies, validação, uploads) e `security-database` (Eloquent, MySQL,
> backups, LGPD). Acione-as internamente durante o audit completo.

> **Regra do projeto:** não inventar regra de negócio. O que vier do edital é citado como
> **(§x)**, decisão do responsável como **(P#)**. O que ainda está em aberto
> (`docs/06-perguntas-em-aberto.md`) entra no relatório como **⚠️ A definir (P#)**, nunca como fato.

---

## Passo 1 — Contexto do Projeto

Antes de iniciar:

1. Ler `README.md`, `docs/02-stack.md` e `docs/03-arquitetura.md` (stack, superfícies, regras).
2. Quais camadas auditar? Frontend, backend, banco, infraestrutura (HostGator) ou todas?
3. Já existe código Laravel no repositório? (`composer.json`, `app/`, `routes/`)
   - Sim → **analise o código diretamente**.
   - Não (só documentação) → audite o **desenho** (docs/03, docs/04, docs/07) e entregue o checklist como requisitos de implementação.
4. O sistema já está no ar? Datas críticas do edital (§7): inscrições a partir de 28/09/2026, avaliação e voto popular de 29/10 a 20/11, resultado em 21/11.

**Superfícies e atores (docs/03):**

| Superfície | Ator | Auth |
|---|---|---|
| Site público + galeria + resultados | Visitante | nenhuma |
| Área do participante | Participante (PF/PJ) | Fortify (páginas próprias) |
| Voto popular | Eleitor | Fortify + Turnstile (P52) |
| Painel `/jurado` | Jurado | Filament (conta criada pelo admin, P17) |
| Painel `/admin` | Organização | Filament |

---

## Passo 2 — Execução da Auditoria

Documente cada achado no formato:

```
[SEVERIDADE] Título do Problema
Arquivo/Módulo: app/Actions/..., routes/web.php (se conhecido)
Descrição: O que está errado e por que é perigoso
Evidência: Trecho de código ou padrão identificado
Correção: Código/configuração exata para corrigir
Regra: §x / P# (se a falha quebra uma regra do edital ou decisão confirmada)
```

### CATEGORIA 1 — Autenticação (Fortify + Filament)

- [ ] Senhas com hash (`'password' => 'hashed'` no cast do `User`) — nunca texto puro
- [ ] Rate limit de login do Fortify configurado (`RateLimiter::for('login', ...)` no `FortifyServiceProvider`)
- [ ] Reset de senha com rate limit e token expirável (`config/auth.php` → `passwords.users.expire`/`throttle`)
- [ ] Cadastro de **eleitor** protegido por Turnstile validado **no servidor** + limite de 5 cadastros por IP por hora (P50, P52)
- [ ] Cadastro de **participante** sem confirmação de e-mail (P73) → garantir que isso não abre brecha (ex.: enumeração de e-mails na tela de cadastro/login)
- [ ] Jurado e admin **não** se cadastram sozinhos: contas criadas pelo admin (P17); rota de registro do Fortify não cria usuários com `role` arbitrário
- [ ] Sessão: `SESSION_SECURE_COOKIE=true` e `SESSION_HTTP_ONLY` (padrão) em produção; `session()->regenerate()` após login (Fortify já faz)
- [ ] Logout invalida a sessão

**Vetores:** criar conta com `role=admin` via campo extra no POST de registro; brute force no login do painel Filament; bots criando eleitores em massa.

---

### CATEGORIA 2 — Autorização (Policies, Gates, painéis)

- [ ] `User` implementa `FilamentUser` com `canAccessPanel()` checando `role` por painel (`admin` → `/admin`, `jurado` → `/jurado`)
- [ ] Cada Resource do Filament tem **Policy** (sem Policy o Resource fica liberado para quem entra no painel)
- [ ] Jurado só vê/avalia **os projetos atribuídos a ele** (§5) — query do Resource escopada (`modifyQueryUsing` / `getEloquentQuery`) **e** Policy
- [ ] Notas **travadas** após envio (P19): Policy de `update` nega para o jurado; só a organização corrige, com justificativa (P46)
- [ ] Participante só vê/edita **as próprias** inscrições (IDOR) e só até o fim do prazo e antes da aprovação (P8, P70)
- [ ] Componentes Livewire chamam `$this->authorize()` nas actions — não confiam em botão escondido na view
- [ ] Contagem do voto popular oculta ao público até a publicação (P39); admin vê a parcial (P48)
- [ ] Resultado só aparece depois do botão **Publicar resultado** (P69)

**Padrão seguro (ownership):**
```php
// ✅ CORRETO — escopo pelo usuário logado + Policy
$entry = $request->user()->entries()->findOrFail($id);
$this->authorize('update', $entry);

// ❌ ERRADO — confia só no ID da URL
$entry = Entry::findOrFail($id);
```

---

### CATEGORIA 3 — Injeções e Mass Assignment

- [ ] `whereRaw`, `orderByRaw`, `selectRaw`, `havingRaw`, `DB::raw`, `DB::statement` com input concatenado → CRÍTICO
- [ ] `orderBy($request->sort)` / `->where($request->campo, ...)` sem allowlist (nomes de coluna **não** são parametrizados)
- [ ] `Model::create($request->all())` ou `->fill($request->all())` → mass assignment (ex.: `role`, `status`, `final_score`)
- [ ] Models com `$fillable` explícito (evitar `$guarded = []`)
- [ ] `$request->validated()` usado depois do Form Request, nunca `$request->all()`
- [ ] `Rule::unique(...)->ignore($id)` nunca recebe ID vindo do usuário

> Detalhes e exemplos: `references/eloquent-injection.md`

---

### CATEGORIA 4 — XSS, CSRF e Headers HTTP

- [ ] `{!! !!}` no Blade com dado de usuário (textos da inscrição, bio de jurado, nome de parceiro) → CRÍTICO
- [ ] Links vindos do participante (`entry_links`) validados como `url` com esquema `http/https` (bloquear `javascript:`)
- [ ] CSRF ativo no grupo `web` (padrão do Laravel); nenhuma rota de formulário excluída sem motivo
- [ ] Middleware de headers de segurança (o Laravel não adiciona por padrão): `X-Content-Type-Options`, `X-Frame-Options`/`frame-ancestors`, `Referrer-Policy`, `Strict-Transport-Security` (com HTTPS), CSP compatível com Livewire/Alpine e Turnstile

> Detalhes: `references/laravel-hardening.md` e skill `security-frontend`

---

### CATEGORIA 5 — Uploads (Media Library)

- [ ] Imagens: validação de tipo (`mimes`/`mimetypes`) e tamanho no servidor (⚠️ limites: P6)
- [ ] Comprovação: **1 PDF de até 10 MB** (P54) — `mimetypes:application/pdf|max:10240`
- [ ] PDF complementar (P7) — ⚠️ limites: P45
- [ ] Comprovações e documentos pessoais em **disco privado** (fora de `public/`), servidos por rota com Policy ou URL assinada
- [ ] Nome do arquivo salvo gerado pelo sistema (nunca o nome original como caminho)
- [ ] Nenhum upload aceita `.php`, `.phtml`, `.svg` com script, `.html`
- [ ] Upload temporário do Livewire com regras em `config/livewire.php` (`temporary_file_upload.rules`)

---

### CATEGORIA 6 — Rate Limiting & Abuso

- [ ] Login (participante, eleitor, painéis), reset de senha e cadastro com `RateLimiter`
- [ ] Cadastro de eleitor: 5 por IP por hora (P52) — lembrar que redes de universidade/empresa compartilham IP
- [ ] Endpoint de voto com throttle; unicidade garantida **no banco**: 1 voto por categoria (`popular_votes` único `(voter_id, category_id)`, P36)
- [ ] Voto só em inscrições **aprovadas** (P37) e só dentro do período (P38), checado no servidor (fuso `America/Fortaleza`)
- [ ] Driver do rate limiter/cache funciona sem Redis (`CACHE_STORE=database`, docs/02)

---

### CATEGORIA 7 — Exposição de Dados Sensíveis (LGPD, §8)

- [ ] `APP_DEBUG=false` em produção (com `true`, a página de erro expõe `.env`, queries e caminhos) → CRÍTICO
- [ ] `$hidden` no `User` (`password`, `remember_token`) e nos perfis (CPF/CNPJ, telefone)
- [ ] Galeria/resultados públicos expõem **só** o que foi confirmado: galeria = nome, categoria, imagens (P55) + distinção e nota final após 21/11 (P57); resultados = P44. Nunca CPF, e-mail, telefone
- [ ] Logs (`storage/logs`) não gravam CPF, senha, token de reset
- [ ] IP de aceites e de cadastro de eleitor (P11, P52, P53) tratado como dado pessoal
- [ ] ⚠️ Política de privacidade e responsável de LGPD: P28

---

### CATEGORIA 8 — Infraestrutura (HostGator compartilhada, sem SSH)

- [ ] Raiz do subdomínio aponta para `public/` (⚠️ P68). Teste: `https://<subdominio>/.env` e `/composer.json` devem dar 404/403
- [ ] `.env` fora de `public_html` e sem permissão de leitura pública
- [ ] HTTPS ativo no subdomínio + redirecionamento HTTP→HTTPS
- [ ] Cron de migrations/comandos **removido** depois de usado (docs/02)
- [ ] "MySQL remoto" no cPanel sem hosts liberados
- [ ] Permissões: nada em `777`; `storage/` e `bootstrap/cache/` graváveis pelo PHP
- [ ] Credenciais de FTP/cPanel fortes; FTPS em vez de FTP

> Checklist completo: `references/hostgator-shared.md`

---

### CATEGORIA 9 — Supply Chain & Dependências

- [ ] `composer.lock` e `package-lock.json` commitados
- [ ] `composer audit` sem vulnerabilidades; `npm audit --audit-level=high` limpo
- [ ] Pacotes conferidos em `docs/02-stack.md` (compatibilidade com Laravel 13 verificada); pacote novo → conferir mantenedor, downloads e compatibilidade antes de instalar
- [ ] Pacote do Turnstile: ⚠️ versão ainda não verificada (docs/02)
- [ ] `vendor/` enviado ao servidor gerado com `--no-dev` (sem ferramentas de debug em produção)

---

## Passo 3 — Relatório Final

```markdown
# 🔐 Relatório de Auditoria de Segurança
**Projeto:** Prêmio Ceará Tem Design 2026
**Data:** [data]
**Stack:** Laravel 13 · PHP 8.4 · MySQL · Fortify · Filament 5 · Livewire 4 · HostGator compartilhada

## Resumo Executivo
[2-3 frases sobre o estado geral]

## Achados por Severidade

### 🔴 CRÍTICO (N achados)
[Corrigir ANTES de ir pra produção]

### 🟠 ALTO (N achados)
[Corrigir em menos de 1 semana / antes da próxima data do edital]

### 🟡 MÉDIO (N achados)

### 🟢 BAIXO / INFORMATIVO (N achados)

## ⚠️ Dependem de decisão (P#)
[Itens que não dá para fechar sem resposta da organização]

## Plano de Ação Priorizado
1. [Ação — o quê, onde, prazo relativo ao §7]

## O que está BEM ✅
```

---

## Referências

- `references/laravel-hardening.md` — configuração segura de Laravel, Fortify, Filament e Livewire
- `references/eloquent-injection.md` — vetores de injeção e mass assignment no Eloquent/Query Builder
- `references/hostgator-shared.md` — checklist de hospedagem compartilhada sem SSH (deploy, cron, `.env`, permissões)

---

## Notas de Contexto (Prêmio Ceará Tem Design)

- **Prazo curto e evento único** (P32): priorizar o que protege inscrições, notas e votos; evitar infraestrutura que a HostGator compartilhada não suporta (Redis, Supervisor, SSH).
- **Integridade das notas** (§5, §6): nota 1 a 5 validada na aplicação; `CHECK` no banco só vale no MySQL 8.0.16+ (⚠️ P65). Correções só pela organização, com histórico e justificativa (P19, P46).
- **Integridade do voto popular** (P35–P60): CPF único entre eleitores (P35), 1 voto por categoria (P36), Turnstile + limite por IP (P52). ⚠️ Peso no resultado: P34.
- **Dados pessoais** (§8): CPF/CNPJ, telefone, cidade/UF (P4), IPs de aceite (P53). ⚠️ LGPD: P28.
