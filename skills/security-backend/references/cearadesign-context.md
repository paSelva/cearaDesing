# Contexto do Projeto — Prêmio Ceará Tem Design 2026

Resumo do que importa para segurança. **A fonte de verdade é `docs/`** — se algo aqui
divergir de lá, vale `docs/`. Convenção: **(§x)** edital · **(P#)** decisão confirmada ·
**⚠️ P#** pergunta em aberto (`docs/06-perguntas-em-aberto.md`). Não preencher lacunas
por conta própria: perguntar.

---

## Stack e hospedagem (docs/02)

- PHP 8.4 · Laravel 13 · MySQL (⚠️ versão 5.7 ou 8.0: P65)
- Fortify (participante e eleitor, páginas próprias) · Filament 5 (`/admin`, `/jurado`) · Livewire 4 · Blade · Tailwind 4 · Pest
- Spatie Media Library (uploads) · Spatie Activity Log (histórico, P46) · Cloudflare Turnstile (P52)
- HostGator **compartilhada**, **sem SSH** (P66, P67), subdomínio de domínio existente (P29, ⚠️ nome: P62)
- Sem Redis garantido → cache, sessão e fila em `database`; fila roda por cron
- Deploy: build local → FTP/cPanel → migrations por cron temporário · raiz em `public/` (⚠️ P68)
- E-mail transacional: ⚠️ P31 (necessário para reset de senha)
- Sistema só para a edição 2026 (P32)

## Atores e papéis (docs/03, T2)

`users.role` ∈ `admin`, `jurado`, `participante`, `eleitor` + Policies (sem pacote de permissões).
Perfis 1:1: `participant_profiles`, `judge_profiles`, `voter_profiles`.

## Regras com impacto em segurança

| Regra | Fonte |
|---|---|
| 1 projeto por categoria por participante | §4 |
| Inscrição gratuita | §4 |
| 3 jurados por projeto, 4 critérios, notas 1–5, máx. 60 | §5 |
| Jurado vê inscrição completa, com autoria | P13 |
| Notas travam após envio; só a organização corrige, com histórico e justificativa | P19, P46 |
| Jurado não comenta, só dá nota | P18 |
| Conta de jurado criada pelo admin | P17 |
| Conflito: organização marca e substitui | P16, P47 |
| Participante edita até o prazo, exceto depois de aprovado | P8, P70 |
| Cadastro do participante: CPF ou CNPJ, telefone, cidade/UF, tipo; sem confirmação de e-mail | P4, P73 |
| Comprovação dentro do sistema: 1 PDF até 10 MB | P12, P54 |
| Imagens: quantidade, formato, tamanho | ⚠️ P6 |
| PDF complementar: limites | ⚠️ P45 |
| Aceites registrados com data/hora e IP | P11, P53 |
| Término das inscrições: 28/10/2026 23:59:59, horário de Fortaleza | §7, P77 |
| Resultado publicado por botão do admin | P69 |
| Declaração de uso de IA | §8 |
| Dados pessoais só para organização, avaliação e comunicação | §8 |
| Política de privacidade / responsável LGPD | ⚠️ P28 |

## Voto popular (fora do edital, P1)

| Regra | Fonte |
|---|---|
| CPF único entre eleitores (pode coincidir com conta de participante) | P35, P60 |
| Validação do CPF só pelo dígito verificador | P42 |
| 1 voto por categoria | P36 |
| Só em inscrições aprovadas | P37 |
| Período 29/10 → 20/11/2026 | P38 |
| Contagem oculta ao público até publicar; admin vê parcial | P39, P48 |
| Turnstile + 5 cadastros de eleitor por IP por hora | P50, P52 |
| Peso no resultado | ⚠️ P34 |

## Dados pessoais armazenados

CPF/CNPJ, nome, e-mail, telefone, cidade/UF (P4) · CPF do eleitor (P35) · IP de cadastro do
eleitor (P52) e dos aceites (P53) · comprovações em PDF (P54) · autoria e créditos das
inscrições (§4).

## O que é público

- Galeria (a partir de 29/10): nome, categoria, imagens (P55); após 21/11, distinção e nota final (P57)
- Resultados: nome, categoria, distinção, nota final, autoria e créditos, imagens e textos (P44)
- Avaliadores: nome, foto e mini bio (P59), desde o lançamento (P61)
