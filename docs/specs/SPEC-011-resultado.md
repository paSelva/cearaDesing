# SPEC-011 — Publicação do resultado

Status: implementação DEV concluída, aguardando revisão. Base: `feature/spec-010-votacao`. Branch: `feature/spec-011-resultado`.

## Objetivo e escopo

A organização publica o resultado pelo botão "Publicar resultado" (P69). A partir daí: página pública de premiados (P21, P44) com o mais votado do voto popular de cada categoria (P51); a galeria passa a mostrar distinção e nota (P57, já preparado na SPEC-010); cada participante vê a nota final e a distinção na área dele (P22). As notas por jurado e por critério nunca são exibidas (P22).

**Fora do escopo:** despublicar (não previsto em `docs/` — pergunta, se necessário); e-mail avisando o resultado (⚠️ P25); certificados (fora do sistema, P23); pedidos de revisão (⚠️ P20); peso do voto popular no resultado (⚠️ P34 — hoje é resultado separado, sem alterar a nota).

### Critérios verificáveis

1. "Publicar resultado" aparece na configuração da edição (`/admin` → Configuração da edição) enquanto não houver publicação; o modal diz o que falta, se faltar.
2. A publicação é bloqueada (Recomendação do `docs/07`) se houver inscrição aprovada sem resultado calculado, voto popular com votos e sem apuração, ou empate do voto popular sem decisão (P49).
3. Publicar grava `editions.results_published_at`, registra no activity log (`resultado`) e não pode ser repetido.
4. `/premiados`: antes da publicação, só o aviso com a data prevista (§7); depois, por categoria e distinção (Ouro, Prata, Bronze, Menção Honrosa), nome, distinção, nota final, autoria e créditos, textos do §4 e até 4 imagens (P44). Projetos sem distinção não aparecem ali (continuam na galeria). Nada de dados de conta do participante.
5. O mais votado de cada categoria aparece na mesma página (P51), desde que continue aprovado (P78).
6. Na área do participante, o painel e a página da inscrição mostram "Resultado: <distinção>, <nota> pontos" só depois da publicação (P22).
7. O site mostra "Premiados" no menu e "Ver os premiados" na capa depois da publicação.

## Dados e operações

- Action `App\Actions\Results\PublishResults` (`pendingItems` + `handle`, com trava da edição).
- `ResultsController` e `resources/views/results/index.blade.php`; imagens pela rota pública da galeria.
- Sem migration: `editions.results_published_at` já existia (SPEC-001).

## Evidência DEV

- `php artisan test --compact`: **338 testes, 883 assertions** (9 novos em `tests/Feature/Results/PublishResultsTest.php`).
- Pint (escopo do CI), `validate-foundation.sh` e `npm run build` passando.
- Navegador: não executado; a página de premiados é renderizada por HTTP nos testes.

## Pendências

- Nada está no ar (P2, P62, P65, P68).
- Perguntas novas: despublicar/corrigir após a publicação? (hoje, correção de nota continua possível e a página reflete na hora — P80.)
