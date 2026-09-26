# Prêmio Ceará Tem Design — Plataforma de Inscrição e Avaliação

Sistema web do **1º Prêmio Ceará Tem Design 2026** (Associação Ceará Design).

> **Escopo:** o edital (`anexos/EDITAL FINAL_PREMIO CEARA TEM DESIGN.pdf`) prevê **avaliação por jurados**: 3 jurados por projeto, 4 critérios, notas de 1 a 5, máximo de 60 pontos (§5). Além disso, haverá **voto popular** (confirmado em 26/09, P1). Ele não está no edital, e as regras dele ainda estão em aberto (P34–P41).

## Stack

- **Definido:** PHP + Laravel.
- **Definido:** PHP 8.4 · MySQL · auth do participante e do eleitor em páginas próprias (Fortify).
- **Recomendado:** Laravel 13 + Filament 5 (admin e jurado) + Blade/Livewire 4/Tailwind 4 + Pest.
- **Hospedagem:** HostGator, plano compartilhado, em um subdomínio do domínio atual. **Sem SSH** (P66). ⚠️ Versão do MySQL (P65) a confirmar.
- **A definir:** e-mail transacional e o nome do subdomínio.

Os detalhes estão em [docs/02-stack.md](docs/02-stack.md).

## Documentação

1. [Escopo e regras do edital](docs/01-escopo-edital.md)
2. [Stack e tecnologias](docs/02-stack.md)
3. [Arquitetura](docs/03-arquitetura.md)
4. [Modelo de dados](docs/04-modelo-dados.md)
5. [Roadmap e cronograma](docs/05-roadmap.md)
6. [Perguntas em aberto](docs/06-perguntas-em-aberto.md) ← **o que ainda precisa ser respondido**
7. [Actions (casos de uso)](docs/07-actions.md)

Convenção: **(§x)** = regra do edital · **(P#, confirmado)** = decisão do responsável que não está no edital · **Recomendação** = proposta técnica · **⚠️ A definir (P#)** = pergunta em aberto.

## Referência

- Site de referência: <https://www.premiobornancini.com.br/>, com o portal de inscrição separado em <https://www.inscricaopremiobornancini.com.br/>
- Edital: `anexos/EDITAL FINAL_PREMIO CEARA TEM DESIGN.pdf` (não versionado: fica fora do repositório, na pasta local `anexos/`)

## Skills do Claude Code

A pasta [`skills/`](skills/) tem skills adaptadas a este projeto (Laravel, MySQL, HostGator): `code-best-practices`, `testing`, `github-security`, `security-audit`, `security-backend`, `security-database` e `security-frontend`. O link `.claude/skills → ../skills` faz o Claude Code carregá-las ao abrir o repositório.
