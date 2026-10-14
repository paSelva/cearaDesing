# Guia rápido — deploy no cPanel (HostGator)

Use este guia para **atualizar** o site depois que ele já está no ar. A primeira instalação
(banco, `.env`, administrador inicial e domínio) está em
[deploy-hostgator.md](deploy-hostgator.md), seção 0.

Tempo típico: 10 a 15 minutos. Não há SSH nesta conta. Tudo é feito pelo GitHub e pelo
cPanel.

## Como funciona

```text
main (código aprovado)
  │  GitHub Actions: "Release para o cPanel (branch producao)" com o SHA
  ▼
branch producao (código + vendor/ + public/build/ + .cpanel.yml)
  │  cPanel: Git Version Control → Update from Remote → Deploy HEAD Commit
  ▼
/home1/chega101/ceara-design/
  ├── current -> releases/SHA      ← o site (Document Root = current/public)
  ├── releases/SHA                 ← uma pasta por versão (guarda a ativa e a anterior)
  └── shared/.env e shared/storage ← configuração e uploads (nunca substituídos)
```

O instalador (`deploy/install-release.sh`) copia a versão, roda as migrations e só troca o
`current` se tudo der certo. **Se falhar, o site continua na versão anterior.**

## Dados desta instalação

| Item | Valor |
|---|---|
| Site | `https://acdesign.chegadim.com.br` |
| Usuário cPanel / pasta pessoal | `chega101` / `/home1/chega101` |
| Clone do Git no cPanel | `/home1/chega101/repositories/cearaDesing` (branch `producao`) |
| Pasta da aplicação | `/home1/chega101/ceara-design` |
| Document Root | `/home1/chega101/ceara-design/current/public` |
| PHP | 8.4 (`ea-php84`) no site; CLI `/opt/cpanel/ea-php84/root/usr/bin/php` (variável `PHP_CLI` no GitHub) |
| Banco | `chega101_cearadesign` (Percona Server 5.7.44-48) |

---

## 1. Antes do deploy

- [ ] A mudança foi mergeada na **`main`** e o CI da `main` está verde.
- [ ] Copie o **SHA completo** (40 caracteres) do commit que vai para o ar: na página do
      commit no GitHub ou com `git rev-parse origin/main`.
- [ ] **Tem migration nova?** Compare `database/migrations/` com a versão no ar.
      Se tiver:
  - [ ] confira que ela é **aditiva** (cria tabela ou coluna, sem apagar ou renomear o que
        a versão atual usa);
  - [ ] faça **backup do banco**: cPanel → phpMyAdmin → `chega101_cearadesign` →
        **Exportar** → Rápido → SQL → **Executar**, e guarde o arquivo fora da hospedagem.
- [ ] Evite fazer deploy durante prazos críticos do edital (fechamento das inscrições,
      votação), a menos que seja uma correção urgente.

## 2. Gerar a release no GitHub

1. GitHub → **Actions** → **Release para o cPanel (branch producao)** → **Run workflow**.
2. **Branch:** `main`. **sha:** o SHA completo do passo 1.
3. Aguarde o check verde (~3 a 5 minutos). O resumo do job mostra
   `Release ... publicada em producao`.

Pelo terminal, a alternativa é:

```bash
gh workflow run release-cpanel.yml --ref main -f sha=SHA_COMPLETO
gh run watch "$(gh run list --workflow release-cpanel.yml --limit 1 --json databaseId --jq '.[0].databaseId')"
```

O workflow recusa SHA que não esteja na `main` e execução a partir de outro branch.

## 3. Implantar no cPanel

1. cPanel → **Git Version Control** → `cearaDesing` → **Manage** → aba **Pull or Deploy**.
2. Clique em **Update from Remote**. O **HEAD Commit** deve mostrar
   `release: SHA_COMPLETO` com autor `github-actions[bot]`.
3. Clique **uma vez** em **Deploy HEAD Commit**.
4. Aguarde de 2 a 5 minutos e atualize a página. Em **Last Deployment Information**, o
   **Last Deployed SHA** deve ser o do commit `release: ...`.

> A mensagem "The checked-out branch includes new deployable changes" significa que a
> release **ainda não foi implantada**. Falta clicar em **Deploy HEAD Commit**.

## 4. Conferir

1. **Log do instalador:** no Gerenciador de Arquivos, abra
   `/home1/chega101/ceara-design-install.log` e vá ao final. Deve terminar com:
   ```text
   current -> /home1/chega101/ceara-design/releases/SHA_COMPLETO
   == Deploy SHA_COMPLETO ativo
   ```
   O detalhe das migrations fica em
   `ceara-design/shared/storage/logs/deploy-SHA_COMPLETO.log`.
2. **Smoke test** (de qualquer computador com o repositório):
   ```bash
   bash scripts/smoke-producao.sh https://acdesign.chegadim.com.br
   ```
   Tudo deve sair `ok`. O `/.env` com **406** é o firewall (Mod_Security) bloqueando, o
   que está correto.
3. **Manual:** abra a home, entre no `/admin` e confira a funcionalidade que mudou.

## 5. Voltar para a versão anterior (rollback)

A versão anterior fica guardada em `releases/`.

1. Rode o workflow do passo 2 com o **SHA da versão anterior**.
2. Repita o passo 3.

O banco **não volta**. Se a versão nova tinha migration, confirme antes que a versão
anterior funciona com as tabelas novas. Se não funcionar, é preciso restaurar o backup do
passo 1 pelo phpMyAdmin, o que perde tudo o que foi gravado depois dele. Combine isso com
a organização antes de fazer.

---

## Problemas conhecidos e soluções

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| Deploy "termina" em menos de 1 segundo e o **Last Deployed SHA** não muda | O instalador parou numa verificação inicial | Leia o final de `/home1/chega101/ceara-design-install.log`. Também dá para ver em `/home1/chega101/.cpanel/logs/vc_*_git_deploy.log` (mais recente). |
| `ERRO: pasta base não encontrada` | Pasta com nome diferente de `ceara-design` (maiúsculas contam) | A pasta precisa ser exatamente `/home1/chega101/ceara-design`. |
| `ERRO: crie .../shared/.env` | `.env` ausente ou com outro nome | O arquivo deve ser `ceara-design/shared/.env`. Ative "Mostrar arquivos ocultos". |
| `.env deve usar permissão 600 ou 640` | Permissão alterada | Gerenciador de Arquivos → Change Permissions → 600. |
| `APP_KEY ausente ou inválida` | Chave mal colada | Formato `APP_KEY=base64:` + 44 caracteres terminando em `=`, sem aspas. **Nunca troque uma chave que já está em uso.** |
| `Falha ao conectar ao banco` | Usuário, senha ou host errados no `.env` | Confira em cPanel → Bancos de dados MySQL se o usuário tem acesso ao banco. |
| `outra instalação está em andamento` | Dois deploys ao mesmo tempo | Aguarde o primeiro terminar. Se nenhum estiver rodando, apague `shared/storage/app/.deploy.lock` e repita. |
| Site com **403** | Pasta da release sem permissão de leitura | Desde o PR #17 o instalador já cria com 755. Se acontecer, ajuste `releases/SHA` para 755. |
| "Composer detected issues ... PHP >= 8.4.1" | Site rodando o PHP padrão da conta (8.3) | MultiPHP Manager → marque o domínio → PHP 8.4 → **Aplicar**. O bloco `ea-php84` fica em `current/public/.htaccess` e o instalador o copia nas próximas releases. |
| Site com **500** e página em branco | Erro do Laravel (normal com `APP_DEBUG=false`) | Leia o final de `ceara-design/shared/storage/logs/laravel-AAAA-MM-DD.log`. |
| **Update from Remote** falha | Credencial do GitHub do clone expirou ou o clone ficou "sujo" | Nunca edite arquivos dentro de `repositories/cearaDesing`. Verifique a credencial do clone no GitHub. |

## Nunca faça

- Editar arquivos em `repositories/cearaDesing` ou em `releases/SHA` (são substituídos e
  podem bloquear o deploy).
- Fazer force-push no branch `producao` (o cPanel atualiza por pull e quebraria).
- Trocar `APP_KEY` ou `CPF_HMAC_KEY` no `.env` de um site em uso.
- Recriar o `admin-inicial.ini` (só existe na primeira instalação).
- Rodar `migrate:fresh`, `migrate:rollback` ou apagar tabelas em produção.
- Colar senhas, chaves ou o conteúdo do `.env` em chat, chamado ou commit.
