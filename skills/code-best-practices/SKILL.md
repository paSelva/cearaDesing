---
name: code-best-practices
description: >
  Boas práticas para desenvolver o Prêmio Ceará Tem Design com Claude Code: monólito
  Laravel 13 (PHP 8.4, MySQL) com regra de negócio em Actions, Policies e Enums, Filament
  5 (admin/jurado), Blade + Livewire 4, Fortify, Pest e deploy por SSH + GitHub Actions na HostGator,
  usando docs/ do repositório como memória do projeto (vault Obsidian opcional). Use
  sempre ao criar Action, Policy, migration, componente Livewire ou Resource do Filament,
  revisar arquitetura, refatorar, ou quando o usuário pedir "seguir boas práticas",
  "revisar o código", "como organizar", "estrutura do projeto", "onde coloco essa regra",
  "Action ou controller?", "segundo cérebro", "memória do projeto", "configurar Obsidian",
  ou variações. Acione também em code review/PR, ou quando o usuário disser "está certo
  assim?", "como retomar o projeto" ou "onde deixei".
---

# Boas Práticas de Código — Prêmio Ceará Tem Design

> **Regra nº 1 do projeto:** não inventar regra de negócio, campo, limite ou data.
> Tudo vem do edital **(§x)** ou de decisão confirmada **(P#)**. O que não estiver em `docs/`
> vira pergunta: registrar em `docs/06-perguntas-em-aberto.md` e perguntar ao usuário.

---

## 1. Estrutura de Projeto (docs/03)

**Estilo:** monólito Laravel convencional com a lógica de negócio em classes **Action**.
Sem DDD, sem módulos isolados, sem microsserviços, sem SPA/Inertia (docs/02, docs/03).

```
app/
├── Actions/<Área>/        Regras de negócio — 1 classe por caso de uso, método handle() (docs/07)
├── Enums/                 Status, distinções, papéis (espelham os enums do MySQL)
├── Exceptions/            BusinessRuleException (mensagem em português)
├── Filament/              Painéis /admin e /jurado (T1)
├── Livewire/              Área do participante e voto popular (T1)
├── Http/Controllers/      Site público
├── Http/Requests/         Validação de formato (Form Requests)
├── Models/
├── Policies/              Quem pode ver/editar o quê
└── Rules/                 Cpf, Cnpj, Turnstile (validação de apoio)
config/premio.php          Parâmetros do prêmio
database/migrations/       Espelham docs/04-modelo-dados.md
tests/                     Pest (Unit + Feature)
```

### Regra de camadas (nunca pular)

```
Rota / Resource Filament / Componente Livewire
   → autoriza (Policy) e valida formato (Form Request / rules())
   → chama a Action
       → aplica TODAS as regras de negócio (prazo, status, unicidade...)
       → grava via Eloquent dentro de DB::transaction() quando toca mais de uma tabela
```

- Controller, Resource e componente **não** guardam regra de negócio — só chamam a Action
- A Action **revalida** as invariantes (não confia que a tela checou)
- Regra de negócio violada → `throw new BusinessRuleException('mensagem em português')`
- "Agora" = `now()` no fuso `America/Fortaleza`; prazos vêm de `editions` (§7)
- Parâmetros do edital (faixas do §6, jurados por projeto do §5) vêm do banco/`config/premio.php`, nunca números mágicos no código

---

## 2. Memória do Projeto (docs/ + Obsidian opcional)

> Claude Code não tem memória entre sessões. Neste projeto, a **pasta `docs/` do repositório**
> é a fonte de verdade de contexto e decisões. Um vault Obsidian pode complementar
> (diário, rascunhos), mas nunca substitui `docs/`.

### Hierarquia de verdade

```
Edital (anexos/, fora do git)   ← regras oficiais (§x)
docs/ do repositório            ← escopo, decisões (P#), modelo, Actions, perguntas
Código                          ← implementação
Vault Obsidian (se houver)      ← diário e notas operacionais
```

Divergência entre `docs/` e código → **apontar e perguntar**, nunca resolver em silêncio.

### Mapa de `docs/`

| Arquivo | Papel | Consultar quando |
|---|---|---|
| `README.md` | Briefing (stack, convenções §/P#) | Início de toda sessão |
| `docs/01-escopo-edital.md` | Regras do edital | Dúvida de regra |
| `docs/02-stack.md` | Versões, pacotes, hospedagem, deploy | Instalar pacote, deploy |
| `docs/03-arquitetura.md` | Superfícies, pastas, onde garantir cada regra | Criar módulo |
| `docs/04-modelo-dados.md` | Tabelas, colunas, índices, cálculo do resultado | Migration, model, query |
| `docs/05-roadmap.md` | Fases e checklist (backlog vivo) | Decidir o que fazer agora |
| `docs/06-perguntas-em-aberto.md` | Perguntas P# e respostas | Algo não definido |
| `docs/07-actions.md` | Cada Action com regras e efeitos | Implementar/testar Action |

### Protocolo de sessão

**Início:**
```
Leia README.md e docs/05-roadmap.md.
Me diga o que entendeu do estado atual e o que vamos trabalhar.
Só comece a codar depois que eu confirmar.
```

**Durante:**
- Regra de negócio? → `docs/01`, `docs/07` (e citar §/P# no código/teste)
- Coluna/índice? → `docs/04`
- Pacote/versão/deploy? → `docs/02`
- Não achou? → **perguntar** e registrar em `docs/06`

**Encerramento:**
```
1. docs/05-roadmap.md → marcar [x] o que foi concluído
2. Decisão nova confirmada pelo usuário → registrar no doc certo com (P#)
3. Pergunta nova → docs/06-perguntas-em-aberto.md
4. Se houver vault: entrada no diário + bugs difíceis resolvidos
```

> 📄 Templates do vault Obsidian (opcional): `references/obsidian-second-brain.md`

---

## 3. Convenções de Código

### Nomenclatura (docs/04)

| Tipo | Convenção | Exemplo |
|------|-----------|---------|
| Tabela | inglês, snake_case, plural | `judge_assignments`, `popular_votes` |
| Coluna | inglês, snake_case | `submitted_at`, `final_score` |
| Valor de enum | **português** (aparece no admin) | `rascunho`, `aprovada`, `mencao_honrosa` |
| Model | PascalCase singular | `JudgeAssignment` |
| Action | verbo + objeto, sem sufixo | `SubmitEvaluation`, `CastPopularVote` |
| Policy / Request | sufixo padrão Laravel | `EntryPolicy`, `UpdateEntryRequest` |
| Enum PHP | PascalCase, cases PascalCase | `EntryStatus::Aprovada` |
| Teste | `<Classe>Test.php` | `SubmitEvaluationTest.php` |

- Mensagens para o usuário em **português**
- `declare(strict_types=1);` e tipos em parâmetros/retornos
- Sempre `id` bigint + `created_at`/`updated_at`, salvo exceção documentada em docs/04

### Action (padrão)

```php
final class ApproveEntry
{
    public function handle(Entry $entry, User $admin): void
    {
        if ($entry->status !== EntryStatus::Enviada) {
            throw new BusinessRuleException('Só inscrições enviadas podem ser aprovadas.');
        }

        $entry->update([
            'status'      => EntryStatus::Aprovada,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);
    }
}
```

> Regras reais de cada Action: `docs/07-actions.md`. O exemplo acima é só a forma.

### Tratamento de erros

```php
// app/Exceptions/BusinessRuleException.php
final class BusinessRuleException extends \RuntimeException {}
```

```php
// bootstrap/app.php — vira mensagem amigável em vez de 500
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (BusinessRuleException $e, Request $request) {
        return back()->withErrors(['regra' => $e->getMessage()])->withInput();
    });
})
```

No Livewire/Filament, capturar e exibir como notificação/erro de campo.

---

## 4. Segurança (obrigatório)

Checklist mínimo — detalhes nas skills `security-audit`, `security-backend`, `security-database`, `security-frontend`:

- [ ] Toda rota/Resource/componente com Policy; `canAccessPanel()` por papel
- [ ] `$request->validated()` + `$fillable` explícito (sem mass assignment de `role`, `status`, notas)
- [ ] `{{ }}` no Blade; `{!! !!}` só com justificativa
- [ ] `#[Locked]` em propriedades Livewire que o usuário não pode mudar
- [ ] Rate limit em login, cadastro (P52) e voto
- [ ] Uploads validados no servidor (P54; ⚠️ P6, P45)
- [ ] `APP_DEBUG=false` e `.env` fora da raiz pública em produção (⚠️ P68)
- [ ] Dados pessoais só onde necessário (§8; ⚠️ P28)

---

## 5. Banco de Dados (MySQL + migrations)

- Migrations espelham `docs/04` — mudou o modelo? Atualizar o doc **no mesmo commit**
- Regras de unicidade do edital com **índice único** (§4, P35, P36) + validação
- `CHECK` só funciona no MySQL ≥ 8.0.16 (⚠️ P65) → validação na aplicação é obrigatória
- FKs com `constrained()`; `restrictOnDelete()` onde apagar quebraria histórico (notas, votos)
- `DB::transaction()` em Actions que gravam em mais de uma tabela
- Evitar N+1: `with()`; em dev, `Model::preventLazyLoading(! app()->isProduction())`
- Produção: migrations rodam pelo `deploy/cron-deploy.sh`, chamado via SSH pelo workflow `deploy.yml` (ou por **cron temporário** se não houver SSH) — docs/02. Nunca `migrate:fresh` em produção
- Backup antes de migration em produção

---

## 6. Interface

### Filament (`/admin`, `/jurado`)
- Resource fino: formulário/tabela + Actions do Filament que chamam as Actions de negócio
- Policies para tudo; query do painel do jurado escopada às atribuições dele

### Blade + Livewire (site, participante, voto)
- Componentes pequenos; estado mínimo nas propriedades públicas
- Validação em `rules()`/`#[Validate]`, regra de negócio na Action
- Tailwind 4; identidade visual do site (⚠️ conteúdo: P27)

---

## 7. Testes

Toda Action e toda regra do edital com teste Pest — skill `testing`.
docs/04 §9 lista as fronteiras obrigatórias do cálculo do resultado.

---

## 8. Git e Commits

### Conventional Commits (em português, citando a regra quando houver)
```
feat(inscricao): bloqueia segunda inscrição na mesma categoria (§4)
fix(voto): compara período usando fuso America/Fortaleza (P38)
refactor: move cálculo da nota final para CalculateEntryResult
docs: registra resposta da P65 (MySQL 8.0)
chore: atualiza filament para 5.x.y
test: cobre fronteiras das faixas de distinção (§6)
```

### Branches
```
main       → sempre deployável
feat/nome  → funcionalidades
fix/nome   → correções
```

### .gitignore
Padrão do Laravel + entradas do projeto (`.DS_Store`, `.idea/`, `anexos/`). Detalhes: skill `github-security`.

---

## 9. Variáveis de Ambiente

- `.env` nunca no git; `.env.example` commitado e atualizado junto com o código
- Código lê `config('...')`, nunca `env()` fora de `config/` (com cache de config, `env()` retorna `null`)
- Segredos: `APP_KEY`, `DB_PASSWORD`, `MAIL_*` (⚠️ P31), chave secreta do Turnstile (P52)
- Produção: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER/QUEUE_CONNECTION/CACHE_STORE=database`

Template completo do `.env.example`: skill `github-security` (seção 2.2).

---

## 10. Checklist de Entrega

- [ ] Regra de negócio na Action, citando §/P#
- [ ] Policy cobrindo quem pode chamar
- [ ] Form Request / `rules()` validando o formato
- [ ] Migration alinhada com `docs/04` (e doc atualizado se mudou)
- [ ] Testes Pest (happy path, fronteiras, erro) passando: `php artisan test`
- [ ] Sem `dd()`, `dump()`, `ray()`
- [ ] `.env.example` atualizado se adicionou variável
- [ ] `docs/05-roadmap.md` atualizado; decisões novas registradas; perguntas novas em `docs/06`
- [ ] Nada inventado: todo número/limite/data tem fonte (§/P#) ou está marcado ⚠️

---

## Referências

- `references/obsidian-second-brain.md` — vault Obsidian opcional: estrutura, templates, protocolo, anti-patterns
- `docs/` do repositório — fonte de verdade do projeto
