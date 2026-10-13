# Obsidian como Segundo Cérebro (opcional) — Prêmio Ceará Tem Design

## Filosofia

Claude Code não tem memória entre sessões. Neste projeto, a memória **oficial** é a pasta
`docs/` do repositório (escopo, decisões P#, modelo de dados, Actions, perguntas em aberto).

Um vault Obsidian é **opcional** e serve para o que não pertence ao repositório:
diário de sessões, rascunhos, bugs difíceis, anotações de reunião com a organização.

**Regra:** decisão confirmada vai para `docs/` (versionada, visível para quem pegar o projeto).
O vault só referencia.

---

## Estrutura do Vault

```
vault/
└── projetos/
    └── ceara-tem-design/
        ├── 00-CONTEXTO.md          ← Lido PRIMEIRO (aponta para docs/)
        ├── 04-erros-e-solucoes.md  ← Bugs resolvidos (deploy HostGator, Filament, Livewire...)
        ├── 06-diario.md            ← Log cronológico de sessões
        └── reunioes/               ← Anotações de conversas com a organização
```

Arquivos que **não** existem no vault porque já estão em `docs/`:

| Vault genérico | Neste projeto |
|---|---|
| `01-arquitetura.md` | `docs/02-stack.md` + `docs/03-arquitetura.md` |
| `02-dominio.md` | `docs/01-escopo-edital.md` + `docs/04-modelo-dados.md` + `docs/07-actions.md` |
| `03-tasks.md` | `docs/05-roadmap.md` |
| perguntas abertas | `docs/06-perguntas-em-aberto.md` |

---

## Templates

### `00-CONTEXTO.md`

```markdown
# Prêmio Ceará Tem Design 2026 — Contexto Ativo

## O que é
Sistema de inscrição, avaliação por jurados e voto popular do 1º Prêmio Ceará Tem Design.
Repositório: paSelva/cearaDesing · Fonte de verdade: docs/

## Stack
Ver docs/02-stack.md (Laravel 13, PHP 8.4, MySQL, Filament 5, Livewire 4, HostGator sem SSH, deploy pelo Git do cPanel)

## Estado atual
- Última sessão: AAAA-MM-DD
- O que foi feito: [resumo]
- Próximo passo imediato: [ação específica, com link para o item do docs/05-roadmap.md]

## Contexto crítico
- Não inventar regra: só edital (§) ou decisão (P#); resto vira pergunta em docs/06
- Próxima data do edital (§7): [data e o que acontece]
- Perguntas bloqueantes agora: [P#]

## Links
- [[04-erros-e-solucoes]] | [[06-diario]]
```

### `04-erros-e-solucoes.md`

```markdown
# Erros e Soluções — Prêmio Ceará Tem Design

## Template
### [AAAA-MM-DD] Título descritivo
**Sintoma:** o que apareceu
**Causa raiz:** por que aconteceu
**Solução:** o que resolveu (comando/arquivo)
**Como evitar:** checklist/teste que impede de novo

## Registro
(entradas reais conforme acontecerem — não criar exemplos fictícios)
```

### `06-diario.md`

```markdown
# Diário de Sessões — Prêmio Ceará Tem Design

## AAAA-MM-DD — Sessão: [tema]
**Feito:**
- ...
**Decisões (registradas em docs/ com P#):**
- ...
**Perguntas novas (docs/06):**
- ...
**Próximo passo:**
- ...
```

---

## Protocolo de Sessão com Claude Code

### Início
```
Leia README.md e docs/05-roadmap.md do repositório.
Se o vault estiver disponível, leia também projetos/ceara-tem-design/00-CONTEXTO.md.
Me diga o que entendeu do estado atual e o que vamos trabalhar.
Só comece a codar depois que eu confirmar.
```

### Durante
- Regra de negócio → `docs/01`, `docs/07`
- Modelo/índice → `docs/04`
- Stack/deploy → `docs/02`
- Erro que parece conhecido → `04-erros-e-solucoes.md` do vault
- Não encontrou → perguntar ao usuário e registrar em `docs/06`

### Encerramento
```
1. docs/05-roadmap.md → marcar o que foi concluído
2. Decisões confirmadas → doc correspondente em docs/ (com P#)
3. Vault (se houver): 06-diario.md + 00-CONTEXTO.md "Estado atual"
4. Bug difícil resolvido → 04-erros-e-solucoes.md
```

---

## Integração técnica: obsidian-claude-code-mcp

O plugin `obsidian-claude-code-mcp` expõe o vault via WebSocket para o Claude Code.
Conferir porta e instruções atuais no repositório do plugin antes de configurar.

Trecho sugerido para o `CLAUDE.md` do projeto (só se o vault for usado):

```markdown
## Memória do projeto
- Fonte de verdade: docs/ deste repositório
- Vault Obsidian (opcional): projetos/ceara-tem-design/

## Protocolo obrigatório
1. Ler README.md e docs/05-roadmap.md antes de qualquer ação
2. Não inventar regra: perguntar e registrar em docs/06
3. Conflito entre docs/ e código: perguntar antes de resolver
```

---

## Regras para Claude

1. **Nunca assumir** — se não está em `docs/` (nem no edital), perguntar
2. **Decisão confirmada vai para `docs/`**, não só para o vault
3. **Diário** (se houver vault) em toda sessão produtiva
4. **Não duplicar** — o vault referencia `docs/` e o código, não copia
5. **Sem dados pessoais** de participantes/eleitores no vault (§8, LGPD)
6. **Sem credenciais** no vault (senha do cPanel, FTP, MySQL, `APP_KEY`)

---

## Anti-patterns

| Anti-pattern | Por que é ruim | Alternativa |
|---|---|---|
| Decisão registrada só no vault | Some para quem pega o repositório | Registrar em `docs/` com P# |
| Copiar `docs/04` para o vault | Duplicação desatualiza | Linkar o arquivo |
| Tasks genéricas ("melhorar voto") | Claude não sabe por onde começar | Item específico no `docs/05` com regra (§/P#) |
| Anotar IP/senha do servidor no vault | Vazamento se o vault sincronizar/for compartilhado | Gerenciador de senhas |
| Exemplos fictícios no registro de erros | Parecem fatos em sessões futuras | Registrar só o que aconteceu |
