# SPEC-008 — Site público com o conteúdo do edital

Status: implementação DEV concluída, aguardando revisão. Base: `feature/spec-007-avaliacao`. Branch: `feature/site-publico`.

## Objetivo e escopo

Substituir a página técnica provisória pelo site do prêmio (P26), com o conteúdo do edital, na identidade visual da Associação Ceará Design (organizadora do prêmio e responsável pela supervisão da votação, conforme o responsável em 28/09).

**Fora do escopo (aguardando conteúdo):** Parceiros e Diretoria (P26, ⚠️ P27), Avaliadores com foto e mini bio (P59, P61), galeria (P24, P55), página de premiados (P21, P44), textos institucionais além do edital (⚠️ P27). O código já tem os blocos; eles só aparecem quando houver cadastro.

## Conteúdo (só edital e dados cadastrados)

| Seção (P26) | Fonte |
|---|---|
| Capa | §1 ("Porque o Ceará tem design — e queremos mostrar."), período de inscrições da tabela `editions` (§7), inscrição gratuita (§4) |
| Sobre o prêmio | §1 |
| Categorias | tabela `categories` (textos do §3 semeados na SPEC-001) |
| Avaliação e premiação | §5 (critérios, escala de 1 a 5, 3 jurados, 20 pontos por jurado) e §6 (faixas de `editions`, o que cada distinção recebe) |
| Datas | §7 (lançamento do edital) e datas da tabela `editions` |
| Como participar | §2 (quem pode, relação com o Ceará, período dos projetos de `editions`) e §4 (passos e campos) |
| Regras gerais | §8 (resumo) |
| Rodapé | cearadesign.org.br e @cearatemdesign (rodapé do edital) |

## Identidade visual (cearadesign.org.br)

- Cores do logo e do site da Associação: tinta `#1D1D1B`, cinza `#9C9B9B`, laranja `#EE7D00` (o acento do logo), amarelo `#F8C218`, laranja escuro `#AC5A00` (texto e links, por contraste).
- Fontes do site deles: Roboto (títulos, aqui na versão condensada, como o logo) e Source Sans (texto). Baixadas no build (`laravel-vite-plugin` + Bunny Fonts) e servidas pelo próprio sistema — sem chamada externa em tempo de uso.
- Elemento principal: o acento laranja do logo, grande na capa e como marcador de lista.
- Logo: `public/images/associacao-ceara-design.png`, copiado do site da Associação (305×130). ⚠️ Pedir à organização o logo em alta resolução/vetor e a confirmação de uso.

## Critérios verificáveis

1. `/` mostra as seções acima com textos do edital; categorias, critérios, faixas e datas mudam se forem alterados no admin.
2. O botão "Inscrever projeto" da capa e de "Como participar" só aparece com as inscrições abertas.
3. Parceiros, Diretoria e Avaliadores não aparecem enquanto não houver conteúdo.
4. Sem rolagem horizontal a 375px; foco visível; link "Ir para o conteúdo".

## Pendências

- ⚠️ P27: textos institucionais, parceiros, diretoria; P59: fotos e bios dos avaliadores.
- Telas de login, cadastro, recuperação de senha e área do participante alinhadas à mesma identidade (cabeçalho com o logo, fundo claro, títulos em Roboto Condensed, botões em tinta com hover laranja escuro).
- Nada está no ar (P2, P62, P65, P68).

## Evidência DEV

- `php artisan test`: **289 testes, 725 assertions** (5 novos em `tests/Feature/Site/HomePageTest.php`).
- Prints no Chrome headless: desktop 1440px e celular 375px (sem rolagem horizontal).
