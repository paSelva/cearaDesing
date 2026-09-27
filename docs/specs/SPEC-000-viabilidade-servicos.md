# SPEC-000 — Diagnóstico, preparação técnica e viabilidade operacional

**Data do diagnóstico:** 26/09/2026 (`America/Fortaleza`)

**Base inicial:** `main` em `e05177f`

**Integração:** `develop`, criada a partir de `e05177f`

**Branch:** `chore/spec-000-viabilidade`

**Estado:** preparação local documental concluída; prova Laravel/MySQL e viabilidade remota pendentes.

## 1. Objetivo, escopo e resultado observável

Esta SPEC registra o estado real, torna verificáveis as exigências de fundação, documenta os serviços e o deploy sem SSH e entrega um SHA para QA independente. Não implementa inscrição, júri, votação ou premiação.

Critérios de saída desta execução:

1. O snapshot do repositório e a classificação das fontes estão registrados.
2. Há matriz requisito → fonte → implementação → evidência → situação → próxima ação.
3. Há inventário de serviços e variáveis somente por nome.
4. O repositório bloqueia classes básicas de artefatos sensíveis e possui CI mínimo.
5. O runbook de ambiente, deploy sem SSH, migration controlada e rollback é reproduzível.
6. O QA recebe base, SHA alvo e instruções para worktree isolado.

## 2. Estado real encontrado

- Repositório confirmado: `paSelva/cearaDesing`; `origin` usa HTTPS.
- O `main` estava limpo quanto a arquivos rastreados e sincronizado em `e05177f`.
- Alteração preexistente preservada: `docs/workflow-ceara-design.md`, não rastreada. Ela foi consultada, mas não integra os commits desta SPEC.
- Não existe `AGENTS.md` no clone.
- Antes desta SPEC só existiam README, documentação `docs/01`–`07`, sete skills e suas referências. Não havia aplicação Laravel, `artisan`, manifests, lockfiles, migrations, rotas, código, testes ou workflows.
- O edital PDF citado em `anexos/EDITAL FINAL_PREMIO CEARA TEM DESIGN.pdf` não está no clone; `anexos/` é ignorado por decisão registrada. As referências § desta entrega dependem da transcrição em `docs/01` e não constituem releitura independente do PDF.
- Branches remotas iniciais: somente `main`. Conforme a tarefa, `develop` foi criada a partir da base e publicada antes da branch desta SPEC.

### Runtime observado

| Recurso | Resultado local | Situação |
|---|---|---|
| PHP | comando ausente | bloqueia aplicação e testes Laravel |
| Composer | comando ausente | bloqueia instalação travada e auditoria Composer |
| Node / npm | `v26.8.2` / `11.19.1` | disponível, mas não há `package.json` |
| MySQL CLI | comando ausente | bloqueia prova local com MySQL |
| Docker | `29.3.1` | disponível; uso aguarda P65 e fundação Laravel aprovada |
| GitHub CLI | instalado, autenticação inválida | bloqueia criação do PR via `gh`; push Git é funcional |
| Gitleaks | comando ausente | scan local não executado; CI foi configurada |

## 3. Hierarquia e classificação das fontes

1. Edital/adendo oficial: regra de negócio (`§`). O PDF não estava disponível nesta execução.
2. Decisões confirmadas em `docs/` (`P#`/`T#`), desde que compatíveis com o edital.
3. Esta SPEC aprovada para a execução.
4. Recomendações de arquitetura e skills.
5. Código e ambiente, como evidência do que existe de fato.

| Tema | Classificação | Fonte | Tratamento nesta SPEC |
|---|---|---|---|
| PHP 8.4, Laravel e MySQL | decisão confirmada para linguagem/framework/banco; major do Laravel é recomendação | `docs/02`, T3, P33/P64 | não inicializar Laravel 13 sem confirmar a versão proposta |
| Fortify; Filament só para admin/jurado | decisão confirmada | T1 | requisito para a próxima fundação |
| `role` simples + Policies | decisão confirmada | T2 | requisito para a próxima fundação |
| Voto popular | decisão confirmada, fora do edital | P1, P34–P60 | não implementar antes do adendo/FAQ P41 e das pendências aplicáveis |
| Estudantes somente na 3.7 | decisão confirmada mais restritiva que o edital transcrito | §2, P9, P41 | não ocultar o conflito; formalização pendente |
| Um projeto por categoria | regra do edital | §4 | futura unicidade no banco + servidor |
| Três jurados, quatro notas de 1–5 | regra do edital | §5 | futura validação e teste de concorrência/estado |
| Faixas de distinção | regra do edital | §6 | futura matriz de fronteiras |
| Datas alteráveis | regra do edital + decisão | §7, P77 | persistir e validar no servidor em Fortaleza |
| Monólito + Actions/Policies/Enums | recomendação técnica | `docs/03`, `docs/07` | adotada como arquitetura documentada, sem código nesta SPEC |

## 4. Decisões, recomendações e perguntas abertas

### Confirmadas

- HostGator compartilhada, sem SSH e sem solicitação de SSH (P63, P66, P67).
- PHP 8.4 (T3), MySQL (P33/P64), Fortify para participante/eleitor (T1) e Policies com `role` simples (T2).
- Turnstile e cinco cadastros de eleitor por IP/hora (P50/P52); a validação precisa ocorrer no backend.

### Recomendações técnicas desta SPEC

- Usar `develop` como integração conforme ordem expressa desta tarefa, embora `skills/github-security` recomende GitHub Flow sem branch permanente de integração.
- Fazer build reproduzível no CI/local, empacotar `vendor/` e assets, transferir por FTPS ou Gerenciador de Arquivos e executar CLI apenas por cron temporário controlado.
- Tratar a fila de hospedagem como assíncrona com latência mínima operacional de 15 minutos; prazos continuam validados em cada mutação do backend.

### Abertas e bloqueios

- P2: estratégia para a abertura/formulário provisório.
- P6/P45: quantidade, formatos e tamanhos de imagens/PDF complementar.
- P10, P20, P25, P27, P28, P31 e P34/P41.
- P62: hostname; P65: versão real do MySQL; P68: raiz pública.
- Versões major recomendadas de Laravel/Filament/Livewire ainda não estão confirmadas por lockfile nem decisão explícita.
- Horários das transições, exceto a inscrição (P77), precisam ser formalizados.

## 5. Matriz de aplicação das skills

| Skill/caminho | Instrução aplicável | Aplicação | Evidência |
|---|---|---|---|
| `skills/code-best-practices/SKILL.md` | não inventar regra; Actions/Policies/Enums; atualizar docs | separação §/P/T/recomendação e bloqueio da inicialização não aprovada | esta SPEC e matriz de aderência |
| `skills/github-security/SKILL.md` | auditar secrets, endurecer ignore, CI e commits convencionais | `.gitignore`, `.env.example`, workflow e template de PR | `scripts/validate-foundation.sh`; `.github/` |
| `skills/security-audit/SKILL.md` | auditar desenho quando não há código; priorizar superfícies e HostGator | riscos e controles viraram critérios futuros, não “implementados” | matriz de aderência e runbook |
| `skills/security-backend/SKILL.md` | autoridade no servidor, Policies, CSRF, rate limit, uploads e Turnstile | requisitos obrigatórios registrados para SPECs futuras | matriz SEC e inventário de variáveis |
| `skills/security-database/SKILL.md` | índices, transações, MySQL real, backup e LGPD | P65 bloqueia prova; runbook exige banco descartável e restauração | matriz DB e runbook |
| `skills/security-frontend/SKILL.md` | escapar saída, proteger Livewire/Filament e não expor secrets | critérios de QA futuros; sem frontend para auditar | matriz SEC, estado “não implementado” |
| `skills/testing/SKILL.md` | MySQL compatível, testes por risco, serviços falsos | nenhum teste Laravel inventado sem aplicação; validação de fundação automatizada | workflow e script local |

Referências lidas conforme a execução: Git/Actions/secrets; hardening/HostGator/injeção; contexto e padrões de backend, banco e frontend; setup e padrões de testes. Referências de Obsidian e exemplos completos de features não foram aplicados porque não existe vault nem código de negócio.

## 6. Prova técnica executada

A prova viável é o pipeline da fundação documental:

- valida entregáveis obrigatórios;
- falha se classes sensíveis de arquivos estiverem rastreadas;
- verifica entradas mínimas do `.gitignore`;
- impede segredo aparente em variável `VITE_*` no exemplo;
- passa a validar `composer.lock`/manifest e `package-lock.json` automaticamente quando a aplicação existir;
- executa Gitleaks no histórico no GitHub Actions.

A prova Laravel, build de assets, migration e separação real de storage **não foi executada**: não existe aplicação, a major ainda é recomendação, faltam PHP/Composer/MySQL e P65. Isso bloqueia a próxima fundação técnica, mas não a documentação, Git, CI e preparação de QA desta SPEC.

## 7. Parecer

- **Preparação local:** GO apenas para documentação, Git, CI e instalação controlada da fundação após decisões.
- **Desenvolvimento de módulos:** NO-GO até confirmar a major do Laravel e preparar PHP 8.4/Composer; módulos que dependem de upload/voto continuam sujeitos às perguntas abertas.
- **Deploy QA:** NO-GO até P62/P65/P68, PHP web/CLI, mecanismo de transporte, banco QA e acesso ao cPanel serem comprovados.
- **Produção:** NO-GO. Não houve aplicação, deploy, teste remoto, restauração, e-mail ou Turnstile real.
