# 05 — Roadmap

> As fases seguem as **datas do edital (§7)**. O escopo de cada fase depende das perguntas de [06](06-perguntas-em-aberto.md). Ainda não há estimativa de esforço.

## ⚠️ Prazo crítico
As inscrições abrem em **28/09/2026** (§7). A documentação foi escrita em **26/09/2026**. O que fazer nesse intervalo: 🟡 aguardando a organização (P2).

**Possibilidade (condicionada a P2):** a organização recebe as inscrições por um formulário provisório e depois elas são importadas para o sistema. Só será implementado se P2 confirmar esse caminho, e o formato do formulário ainda não está definido.

## Fase 0 — Setup
- [x] SPEC-000: diagnóstico do repositório, matriz de aderência, CI mínimo e runbook sem SSH (prova remota pendente)
- [ ] Atualizar para PHP 8.4 (T3) e instalar Composer ou Herd
- [ ] Banco MySQL local na mesma versão do servidor (P33, P65)
- [ ] Montar o deploy sem SSH: build local + upload + cron para migrations (P63, P66) · ⚠️ P68
- [ ] Fazer um deploy de teste ("hello world" Laravel 13 + PHP 8.4) na HostGator **antes** de desenvolver as funcionalidades
- [x] Criar o repositório git
- [x] Fundação Laravel 13 com Filament 5 no painel `/admin` (SPEC-001; validação local)
- [x] Migrations e seed idempotente: edição 2026, 9 categorias e 36 critérios (SPEC-001)
- [x] Papéis por campo `role` + Policies para painel e catálogo (SPEC-001)

## Fase 1 — Site público e inscrição
**Prazo do edital:** inscrições de 28/09 a 28/10/2026 (§7).
- [ ] Site público: Hero/Sobre, Categorias/Avaliação, Datas/Como participar, Parceiros/Diretoria, Avaliadores (P26) · ⚠️ P27
- [x] Cadastro e login do participante em páginas próprias (Fortify, T1): PF/PJ, CPF/CNPJ, telefone, cidade/UF, tipo (P3, P4) — SPEC-002; recuperação de senha em produção depende do e-mail (⚠️ P31)
- [x] Formulário de inscrição com os campos do §4, links + PDF (P7), a declaração de IA e os aceites do §8 (P6, P45) — SPEC-003
- [x] Edição permitida até o fim do prazo (P8) — SPEC-003
- [x] Aceites do §8 com um checkbox por item, data/hora e IP (P11, P53) — SPEC-003
- [ ] Fluxo de comprovação: status + upload de 1 PDF de até 10 MB pelo participante (P12, P54), com prazo definido pelo admin em cada solicitação (P56)
- [x] Regra da 3.7: exclusiva para estudantes, e estudantes só na 3.7 (P9) — SPEC-003
- [x] Bloqueio das inscrições ao fim do prazo — SPEC-003
- [x] Área da organização: consultar as inscrições e fazer a triagem (a aprovação libera a votação popular, P37, P43) — SPEC-004 (aprovar/desclassificar; saída da galeria/votação/avaliação entra nas SPECs dessas funções)

## Fase 1b — Voto popular (P1)
- [ ] Galeria pública dos projetos aprovados, onde o público vota (P24): a partir de 29/10, com nome, categoria e imagens (P55). Depois de 21/11, mostra também a distinção e a nota final (P57)
- [ ] Cadastro de eleitor com CPF validado pelo dígito verificador (P35, P42)
- [ ] Cloudflare Turnstile + limite de 5 cadastros de eleitor por IP por hora (P50, P52)
- [ ] 1 voto por categoria (P36), só em projetos aprovados (P37)
- [ ] Votação de 29/10 a 20/11 (P38), com contagem oculta até o fim (P39) e resultado por categoria (P40)
- [ ] Parcial visível só para o admin (P48). Empate decidido pela organização (P49)
- [ ] Resultado do voto popular na página de premiados (P51) · ⚠️ peso no resultado (P34)

## Fase 2 — Avaliação
**Prazo do edital:** 29/10 a 20/11/2026 (§7).
- [x] Admin cria as contas dos jurados, com senha (P17), e define nova senha quando preciso (P82) — SPEC-002; mini bio e foto (P59) ainda pendentes
- [ ] Admin define os 3 jurados fixos de cada categoria (P14, P15) e substitui em caso de conflito (P16): a organização escolhe o substituto daquele projeto (P47)
- [ ] Tela de avaliação: 4 notas de 1 a 5 (§5), sem comentário (P18), travada após o envio (P19)
- [ ] Correção de nota pela organização, com histórico e justificativa obrigatória (P19, P46)
- [ ] Acompanhamento do andamento pela organização

## Fase 3 — Resultado
**Prazo do edital:** divulgação em 21/11 e cerimônia em 26/11/2026 (§7).
- [ ] Cálculo da nota final e da distinção (§5, §6)
- [ ] Botão **Publicar resultado** no admin (P69)
- [ ] Página pública de premiados: nome, categoria, distinção, nota final, autoria, imagens e textos (P21, P22, P44)
- [ ] Nota final visível ao participante na área logada (P22)
- [ ] E-mails (P25) · ⚠️ a definir
- Certificados: fora do sistema (P23)
