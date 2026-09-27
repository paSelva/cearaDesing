# SPEC-000 — Matriz de aderência

Situações: **verificado** = comando/evidência nesta execução; **documentado** = existe apenas como intenção; **bloqueado** = falta decisão/ambiente; **não implementado** = código ausente.

| Requisito | Fonte | Implementação existente | Teste/evidência | Situação | Próxima ação |
|---|---|---|---|---|---|
| Repositório, base e integração identificados | tarefa §11 | `main`; `develop` criada de `e05177f` | `git status`, `branch -a`, `log` | verificado | proteger `main` e `develop` no GitHub |
| Aplicação Laravel travada | `docs/02`; tarefa §8 | inexistente | nenhum manifest, lockfile ou `artisan` | bloqueado | confirmar major e criar fundação em SPEC-001 |
| PHP 8.4 | T3 | ausente localmente | `php -v`: comando ausente | bloqueado | instalar 8.4 e conferir extensões oficiais |
| MySQL compatível | P33/P64/P65 | ausente localmente; versão HostGator desconhecida | `mysql --version`: comando ausente | bloqueado | obter `SELECT VERSION()` do QA e reproduzir via Docker |
| Fortify participante/eleitor | T1 | inexistente | sem `composer.json`/rotas | não implementado | SPEC-001/002 após e-mail definido |
| Filament admin/jurado | T1; recomendação de versão | inexistente | sem código/pacote | não implementado | confirmar versão e instalar na fundação |
| Actions/Policies/Enums | `docs/03`, `docs/07` | inexistentes | sem `app/` | não implementado | testes de arquitetura na SPEC-001 |
| Uma inscrição por categoria | §4 | apenas desenho em `docs/04` | nenhum índice/teste | documentado | migration + teste concorrente na SPEC de inscrição |
| Notas 1–5 e três jurados | §5 | apenas desenho | nenhum código/teste | documentado | servidor + índice/estado; não depender de CHECK (P65) |
| Faixas de distinção | §6 | apenas desenho | fronteiras descritas na skill | documentado | testes 12/35/36/41/42/47/48/53/54/60 |
| Prazos em Fortaleza | §7/P77 | apenas desenho | nenhum teste | documentado | persistir e testar antes/no/depois |
| Voto popular | P1/P34–P60 | apenas desenho | nenhum código/teste | bloqueado parcialmente | formalizar P34/P41 antes da publicação |
| Estudante apenas na 3.7 | P9, em tensão com §2 | apenas desenho | conflito registrado | bloqueado para regra pública | obter adendo/FAQ P41 |
| Autorização por objeto | workflow §8; skills backend/frontend | inexistente | nenhuma rota/Policy | não implementado | Policies e testes IDOR em toda superfície |
| CSRF e rate limits | workflow §8; P52 | inexistente | nenhuma rota | não implementado | middleware/testes; limite de voto não inventado |
| Turnstile backend | P52 | inexistente | docs oficiais consultados | documentado | Siteverify; falhar fechado; hostname/action; testes falsos |
| Storage público/privado | workflow §8; P54/P55 | inexistente | nenhum filesystem da aplicação | bloqueado | prova local e QA após fundação; downloads com Policy |
| `.env` e artefatos sensíveis fora do Git | skills Git | ignore endurecido e exemplo sanitizado | script de fundação | verificado no snapshot | executar Gitleaks no CI e revisar artefato de deploy |
| CI mínima | tarefa §9; skill GitHub | workflow de fundação | script local + checks `Repository foundation` e `Secret detection` aprovados no PR #1 | verificado | manter checks obrigatórios na integração |
| Testes Pest | recomendação em `docs/02`; skill testes | inexistentes | PHP/Composer e app ausentes | bloqueado | introduzir com fundação aprovada e MySQL isolado |
| Build assets | recomendação em `docs/02` | inexistente | sem manifest | bloqueado | `npm ci && npm run build` após lockfile |
| Deploy sem SSH | P66/P67 | runbook; nenhum deploy | sem acesso QA/cPanel | documentado | executar prova somente no QA autorizado |
| Cron | HostGator; workflow | runbook adota mínimo de 15 min | documentação oficial pública; plano real não verificado | bloqueado remotamente | confirmar restrição do plano e PHP CLI |
| Backup e restore | segurança DB | runbook | não executado | bloqueado | restaurar banco + storage em QA isolado |
| E-mail/reset | T1/P31 | inexistente | provedor não decidido | bloqueado | escolher serviço e testar entrega no QA |

## Conflitos e divergências detectados

| Fontes | Divergência | Impacto/encaminhamento |
|---|---|---|
| `skills/github-security` × tarefa/workflow | skill recomenda GitHub Flow sem `develop`; tarefa exige identificar/criar integração | prevaleceu a ordem explícita: `develop`; registrar e não criar `dev` |
| `skills/security-audit/references/hostgator-shared.md` × documentação HostGator/workflow | referência mostra `schedule:run` por minuto; hospedagem compartilhada limita a 15 min | runbook usa `*/15`; corrigir a skill em tarefa separada, não nesta SPEC |
| `docs/02` × estado local | afirma PHP 8.3.33, Composer ausente, Node 24.14.1 e MySQL 9.6; diagnóstico atual encontrou PHP/Composer/MySQL ausentes e Node 26.8.2 | tratar docs como snapshot antigo; atualizar fatos somente após decisão de ambiente |
| `docs/02` | Laravel 13/Filament 5 são recomendação, mas outras skills os tratam como stack fechada | não instalar até confirmação explícita e lockfiles |
| §2 transcrito × P9 | edital permite estudantes; P9 restringe estudantes à 3.7 | exige formalização P41 antes de comunicação/produção |
| edital × P1 | voto popular não consta no edital | P34/P41 impedem apresentá-lo como regra oficial já formalizada |

## Segurança: situação por superfície

Não foram encontradas credenciais rastreadas por nome de arquivo. A ausência de Gitleaks local impede afirmar que o histórico está livre; o scan foi configurado no CI. Como não há aplicação, não existem endpoints, uploads, Livewire, Filament, banco ou storage para testar. Todos os controles dessas camadas permanecem requisitos, não comportamentos implementados.
