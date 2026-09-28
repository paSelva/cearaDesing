# SPEC-010 — Galeria, voto popular, parcial e apuração

Status: implementação DEV concluída, aguardando revisão. Base: `feature/spec-009-eleitor`. Branch: `feature/spec-010-votacao`.

## Escopo
- Galeria pública `/galeria` a partir de `voting_starts_at` (P55), só com inscrições aprovadas (P24, P37), mostrando nome, categoria e imagens (P55); distinção e nota só após `results_published_at` (P57, P69). Imagens públicas só da coleção `images`, cache de 5 min.
- Voto do eleitor (P60) na página do projeto: 1 por categoria (P36), no período de votação (P38), final (P71), inclusive no próprio projeto (P58); categoria tirada da inscrição; IP gravado. Limite de 20 votos/min por eleitor (Recomendação).
- Contagem nunca pública (P39); eleitor vê só os próprios votos em `/eleitor`.
- Admin: "Voto popular → Parcial" (P48, só aprovadas, P78); "Apurar voto popular" só depois de `voting_ends_at`, 1 resultado por categoria com empate marcado (P40); "Decidir empate" só entre os empatados (P49). Reapuração mantém a decisão se o empate não mudou e a limpa se mudou. Activity log `voto_popular`.
- Fora do escopo: o que o voto popular premia/peso no resultado (⚠️ P34), adendo (⚠️ P41), exibição do vencedor na página de premiados (SPEC de publicação, P51).

## Dados
Migration `create_popular_vote_tables` (`popular_votes` conforme docs/04; `popular_vote_winners` com `entry_id` nulo durante empate e `tied_entry_ids` — ajustes técnicos ao docs/04).

## Evidência DEV
329 testes, 852 assertions (24 novos em `tests/Feature/Voting/PopularVoteTest.php`); Pint (escopo do CI), `validate-foundation.sh` e build passando. Navegador não executado.

## Pendências
Nada está no ar (P2, P62, P65, P68); chaves reais do Turnstile e conferência de IP atrás de proxy (SPEC-009).
