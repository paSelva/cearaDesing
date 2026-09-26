# Referência: Obsidian + GitHub

## Novo arquivo no vault: `github-status.md`

Cada projeto no vault deve ter este arquivo em:
```
vault/projetos/[nome-do-projeto]/github-status.md
```

Ele é lido no início de toda sessão que envolva git/GitHub e atualizado no final.

---

## Template: `github-status.md`

```markdown
# [Nome do Projeto] — Status GitHub

## Repositório
- **URL:** https://github.com/seu-usuario/nome-do-repo
- **Visibilidade:** privado | público
- **Branch principal:** main
- **Última sincronização deste arquivo:** YYYY-MM-DD

---

## Branches ativas
| Branch | Criada em | Status | Objetivo |
|--------|-----------|--------|----------|
| main | YYYY-MM-DD | ✅ produção | branch principal |
| feat/nome | YYYY-MM-DD | 🔄 em desenvolvimento | descrição curta |

## Branches arquivadas (já mergeadas)
- `feat/area-do-jurado` — mergeada em YYYY-MM-DD
- `fix/login-token` — mergeada em YYYY-MM-DD

---

## Proteções configuradas
- [ ] Branch protection em `main`
- [ ] Secret Scanning ativado
- [ ] Push Protection ativado
- [ ] Dependabot Alerts ativado
- [ ] Gitleaks como pre-commit hook
- [ ] GitHub Actions security workflow

---

## Secrets configurados no GitHub
> Listar apenas os NOMES, nunca os valores

- `FTP_SERVER` (só se o deploy por FTP via Actions for adotado)
- `FTP_USERNAME`
- `FTP_PASSWORD`

---

## Histórico de auditorias
| Data | Resultado | Ação tomada |
|------|-----------|-------------|
| YYYY-MM-DD | ✅ sem issues | configuração inicial |
| YYYY-MM-DD | ⚠️ .gitignore incompleto | atualizado com template completo |

---

## Issues abertas / pendências GitHub
- [ ] Ativar Dependabot (aguardando acesso ao plano)
- [ ] Configurar workflow de deploy automático

---

## Notas e decisões
- YYYY-MM-DD: Optamos por GitHub Flow ao invés de GitFlow — projeto solo sem necessidade de branches de release
- YYYY-MM-DD: Gitleaks instalado após encontrar .env.local no staging
```

---

## Protocolo de sessão com contexto GitHub

### Início — o que ler

```
Claude: antes de qualquer ação git/GitHub, leia:
1. projetos/[nome]/00-CONTEXTO.md  → stack e estado geral do projeto
2. projetos/[nome]/github-status.md → branches, proteções, secrets configurados
```

### Durante — o que consultar

| Situação | Arquivo a consultar |
|---|---|
| Dúvida sobre qual branch usar | `github-status.md` → "Branches ativas" |
| Secret não encontrado localmente | `github-status.md` → "Secrets configurados no GitHub" |
| Repitir uma auditoria | `github-status.md` → "Histórico de auditorias" |
| Decisão de workflow já tomada | `github-status.md` → "Notas e decisões" |

### Encerramento — o que atualizar

```
Ao final de toda sessão que envolva git/GitHub, atualizar:

1. github-status.md
   → Branches: mover concluídas para "arquivadas", adicionar novas
   → Proteções: marcar o que foi configurado hoje
   → Histórico de auditorias: adicionar entrada com data e resultado
   → Issues: fechar o que foi resolvido, adicionar novas

2. 06-diario.md
   → Entrada: "YYYY-MM-DD — GitHub: [o que foi feito]"

3. 01-arquitetura.md (se houve decisão nova)
   → Ex: "Workflow de deploy via GitHub Actions configurado em YYYY-MM-DD"
```

---

## Como criar `github-status.md` para um projeto novo

Quando o arquivo não existe ainda, criar com este prompt:

```
Leia o vault do projeto [nome] e crie o arquivo github-status.md
com base nas informações disponíveis em 00-CONTEXTO.md.
Use o template de github-status.md da skill github-security.
Preencha o que você sabe e deixe como "não configurado" o que ainda não foi feito.
```

---

## Integração com `00-CONTEXTO.md`

Adicionar esta seção no `00-CONTEXTO.md` de projetos que usam GitHub:

```markdown
## Repositório
- GitHub: https://github.com/usuario/nome-repo
- Branch ativa: feat/modulo-atual
- Estado do git: clean | modificações locais | branch desatualizada
- Ver `github-status.md` para detalhes de proteções e branches
```

---

## Anti-patterns a evitar

❌ **Nunca registrar valores de secrets no vault**
O vault Obsidian é sincronizado e pode ser lido por outros plugins/ferramentas. Registrar apenas os nomes das variáveis.

❌ **Não deixar o `github-status.md` ficar defasado**
Um arquivo com data de 3 meses atrás é pior que nenhum arquivo — passa uma falsa sensação de segurança. Se não vai manter atualizado, melhor não criar.

❌ **Não misturar status de múltiplos projetos num único arquivo**
Cada projeto tem seu próprio `github-status.md` dentro da pasta do projeto no vault.

✅ **Boa prática:** no início de cada sessão, conferir se `github-status.md` está defasado e perguntar ao usuário o que mudou antes de prosseguir.
