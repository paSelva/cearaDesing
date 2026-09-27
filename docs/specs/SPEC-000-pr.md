## Problema e objetivo

O repositório continha documentação e skills, mas ainda não possuía aplicação, lockfiles, CI, matriz de aderência nem processo operacional verificável. Esta entrega reconcilia a SPEC-000 com o estado real e prepara uma fundação segura para iniciar o desenvolvimento sem declarar como pronta a viabilidade remota.

## SPEC e fontes

SPEC-000; edital transcrito em `docs/01` (§2–§8); decisões P/T em `docs/01`–`07`; skills locais de código, Git, segurança, banco, frontend/backend e testes.

## O que mudou

- registra diagnóstico, matriz requisito/fonte/evidência e conflitos;
- inventaria serviços e variáveis somente por nome;
- documenta ambiente, pacote, deploy sem SSH, migration controlada, backup e rollback;
- adiciona handoff de QA por SHA;
- endurece `.gitignore` e inclui `.env.example` sanitizado;
- adiciona validação local e CI mínima com detecção de secrets;
- cria template de PR e atualiza a Fase 0 do roadmap.

## Como validar

1. Fazer checkout isolado da branch.
2. Executar `bash -n scripts/validate-foundation.sh`.
3. Executar `bash scripts/validate-foundation.sh`.
4. Revisar `git diff --check e05177f..HEAD`.
5. Confirmar que `docs/workflow-ceara-design.md` não integra o diff.
6. Conferir os checks `Repository foundation` e `Secret detection`.

## Evidências

- Base do diff: `e05177f`.
- Comandos locais: script de fundação passou; YAML foi carregado; `git diff --check` passou; varredura sanitizada não detectou padrões de credencial.
- Verificações não executadas: Laravel/Pest/build/migrations (aplicação e lockfiles ausentes; PHP/Composer/MySQL ausentes); Gitleaks local (binário ausente); HostGator/QA (sem acesso).
- Parecer QA: pendente, conforme `docs/qa/SPEC-000-handoff.md`.

## Banco, serviços e segurança

Não há migration nesta entrega. Variáveis afetadas estão em `.env.example`, sem valores secretos. P62, P65, P68, e-mail, storage, backup/restore e prova remota permanecem pendentes. Turnstile continua requisito de backend; nenhum segredo vai para `VITE_*`.

## Deploy e rollback

Não houve deploy. O runbook documenta pacote sem `.env`, `.git`, dumps, logs, backups ou caches locais; FTPS/cPanel; document root em `public/`; migration por cron temporário com lock e remoção. Rollback deste PR é a reversão dos commits, pois não altera banco ou serviço.

## Pendências

- confirmar a major do Laravel e preparar PHP 8.4/Composer;
- obter P62/P65/P68 e acesso QA/cPanel;
- validar PHP web/CLI, cron do plano, e-mail, Turnstile, storage e restore;
- formalizar P41 para voto popular e restrição dos estudantes.
