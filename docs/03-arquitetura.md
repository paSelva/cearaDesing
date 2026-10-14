# 03 — Arquitetura

> Este documento é uma **recomendação técnica**. Tudo que depende de uma regra de negócio ainda não confirmada está marcado com ⚠️ A definir (P#).

## Estilo arquitetural (Recomendação)

Pesquisamos três padrões mais pesados:
- **DDD**;
- **monólito modular** com fronteiras isoladas;
- **microsserviços**.

Para este projeto, recomendamos um **monólito Laravel convencional, com a lógica de negócio em classes Action**. O sistema tem um domínio pequeno (inscrição → avaliação → resultado) e um prazo curto, por isso a estrutura padrão do Laravel com Actions, Policies e Enums já basta. Controllers, Resources do Filament e componentes Livewire não guardam regra de negócio: eles chamam uma Action. Assim, a mesma regra vale em todos os pontos de entrada e nos testes.

Referências:
- [Action/Service pattern – DEV Community](https://dev.to/abdulsalamamtech/best-architectural-patterns-approach-for-senior-laravel-developers-8m4)
- [Modular Monoliths – Wendell Adriel](https://wendelladriel.com/blog/modular-monoliths-creating-real-boundaries-before-reaching-for-microservices)
- [Monolith vs Microservices – Ravenna](https://ravennainteractive.com/our-thinking/laravel-monolith-vs-microservices-for-growing-saas)

## Superfícies do sistema

Cada superfície corresponde a um ator do edital.

| Superfície | Ator (edital) | Tecnologia (Recomendação) |
|---|---|---|
| Site público | Visitantes e interessados | Blade + Tailwind (+ Livewire, se necessário) |
| Área do participante | Participantes (§2, §4) | Páginas próprias (Blade + Livewire) com auth via Fortify (T1) |
| Área do jurado | Jurados (§5) | Painel Filament `/jurado` |
| Área da organização | Organização / comissão (§8) | Painel Filament `/admin` |
| Voto popular | Público (P1: confirmado, fora do edital) | Páginas próprias com auth via Fortify (T1) · regras confirmadas em P35–P60 · ⚠️ peso no resultado: P34 |

### Site público
A referência indicada é o [premiobornancini.com.br](https://www.premiobornancini.com.br/). Ela tem:
- menu com Regulamento, Sobre e Inscrição;
- hero;
- tabela de inscrição (preços);
- 11 categorias com imagens;
- números da edição anterior;
- patrocinadores e parceiros;
- diretoria;
- CTA final;
- política de privacidade.

O portal de inscrição da referência fica em outro domínio (inscricaopremiobornancini.com.br), com login por e-mail e senha, cadastro de usuário e recuperação de senha.

Como adaptar ao Ceará Tem Design:
- **Não se aplica:** tabela de preços, porque a inscrição é gratuita (§4).
- **Não se aplica:** números da edição anterior, porque esta é a 1ª edição (§1).
- **Seções (P26, confirmado):**
  - Hero + Sobre o prêmio;
  - Categorias + Avaliação;
  - Datas + Como participar;
  - Parceiros + Diretoria;
  - Avaliadores: nome, foto e mini bio de cada jurado (P59), visível desde o lançamento (P61).
- **Conteúdo:** ⚠️ A definir (P27).
- **Galeria pública de projetos aprovados** (P24), que também é onde acontece o voto popular: visível a partir de 29/10, mostra nome, categoria e imagens (P55) · depois de 21/11, passa a mostrar a distinção e a nota final (P57).
- **Página pública de resultados:** confirmada (P21). Mostra nome, categoria, distinção, nota final, autoria e créditos, imagens e textos da inscrição (P44).

### Área do participante
Do edital:
- inscrever 1 projeto por categoria com os campos do §4;
- declarar o uso de IA (§8);
- dar os aceites (§8).

Confirmado:
- cadastro com CPF ou CNPJ, telefone, cidade/UF e tipo de participante, sem confirmação de e-mail (P4, P73);
- edição permitida até o fim do prazo, exceto depois da aprovação na triagem (P8, P70);
- comprovação pelo sistema: 1 PDF de até 10 MB, com prazo definido pelo admin (P12, P54, P56).

### Área do jurado
Do edital:
- avaliar individualmente os projetos atribuídos, com 4 notas de 1 a 5 (§5).

Confirmado:
- o jurado vê a inscrição completa, com autoria e créditos (P13);
- depois do envio, as notas travam e só a organização corrige (P19);
- o jurado não declara conflito pelo sistema, porque quem identifica é a organização (P16);
- o acesso é criado pelo admin, com senha (P17);
- o jurado só dá notas, sem comentário (P18).

### Área da organização
Do edital:
- substituir jurados em caso de conflito (§8);
- desclassificar (§8);
- analisar pedidos de revisão (§8);
- divulgar resultados (§7).

Confirmado:
- cada categoria tem **3 jurados fixos** (P14), e o admin faz a atribuição manualmente (P15) · em caso de conflito, a organização escolhe o substituto daquele projeto (P47);
- a triagem da inscrição feita pela organização também libera o projeto para a votação popular (P43);
- é a organização que marca o conflito e substitui o jurado (P16);
- é a organização que corrige notas em caso de erro (P19, §8), com histórico e justificativa obrigatória (P46);
- é a organização que cria as contas dos jurados (P17);
- os certificados ficam **fora do sistema** (P23).

⚠️ A definir:
- canal de revisão (P20);
- e-mails (P25).

## Estrutura de pastas (Recomendação)

```
app/
├── Actions/        Regras de negócio (uma classe por caso de uso)
├── Enums/          Status, distinções, papéis
├── Filament/       Painéis admin e jurado (T1)
├── Livewire/       Área do participante e voto popular (T1)
├── Http/Controllers/  Site público
├── Models/
├── Policies/       Quem pode ver/editar o quê
config/premio.php   Parâmetros do prêmio
tests/              Pest (Unit + Feature)
```

A lista completa de Actions, com as regras de cada uma, está em [07-actions.md](07-actions.md). O modelo de dados detalhado está em [04-modelo-dados.md](04-modelo-dados.md).

## Regras do edital e onde garanti-las (Recomendação)

| Regra (edital) | Garantia proposta |
|---|---|
| 1 projeto por categoria por participante (§4) | Índice único no banco + validação |
| Nota de 1 a 5 (§5) | Validação na aplicação; `CHECK` adicional somente no MySQL 8.0.16+. No Percona 5.7.44-48 confirmado em P65, a migration omite o `CHECK` e os testes comprovam a regra na aplicação. |
| 3 jurados por projeto (§5) | Parâmetro da edição (padrão 3) + validação ao atribuir |
| Nota final = soma dos 3 jurados (§5) | Calculada só quando os 3 tiverem avaliado |
| Faixas de distinção (§6) | Parâmetros da edição (as datas e regras podem mudar, §7) |
| Jurado com conflito não avalia (§8) | A organização substitui o jurado na atribuição |
| Prazos (§7) | Datas cadastradas no admin e verificadas no servidor (fuso `America/Fortaleza`) |

## Segurança e LGPD

Do edital:
- dados pessoais usados apenas para organização, avaliação e comunicação do prêmio, conforme a legislação (§8).

Recomendações técnicas padrão do Laravel:
- senhas com hash;
- proteção CSRF;
- rate limit no login;
- validação de tipo e tamanho dos uploads;
- backups.

⚠️ A definir: política de privacidade e responsável de LGPD (P28).
