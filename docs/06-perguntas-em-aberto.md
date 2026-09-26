# 06 — Perguntas em aberto

Tudo o que **não está no edital** e ainda **não foi confirmado** pelo responsável do projeto aparece aqui. Nos outros documentos, esses pontos estão marcados como `⚠️ A definir (P#)`.

**Legenda de status:** 🔴 aberta (ainda não perguntada ou sem resposta) · 🟡 aguardando confirmação da organização · 🟢 respondida (resposta registrada ao lado)

## Escopo

| # | Pergunta | Status |
|---|---|---|
| P1 | Haverá **voto popular**, além da avaliação dos jurados? O edital não prevê. | 🟢 **Sim, haverá voto popular** (26/09). Os detalhes estão em P34–P41. |
| P2 | As inscrições abrem em **28/09/2026** e o sistema não fica pronto até lá. O que fazer: formulário provisório com importação depois, adiar as inscrições, ou a organização já tem outra ferramenta? | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). Enquanto isso, a importação de um formulário provisório fica documentada **como possibilidade** no roadmap. |

## Voto popular (a partir de P1 = sim)

| # | Pergunta | Status |
|---|---|---|
| P34 | O voto popular gera uma **premiação separada** (ex.: "Prêmio do Público") ou **entra na nota** dos jurados? O edital (§5, §6) define a nota final só com os jurados. | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |
| P35 | **Quem pode votar:** qualquer pessoa ou apenas quem tiver cadastro? Como identificar o eleitor (e-mail verificado, CPF, login social, telefone/SMS)? | 🟢 **Cadastro com CPF**: 1 voto por CPF, além do e-mail (26/09). Ver P42. |
| P36 | **Quantos votos por eleitor:** 1 no total, 1 por categoria, ou 1 por projeto? | 🟢 **1 voto por categoria** por eleitor (26/09). |
| P37 | **Quais projetos entram na votação:** todos os inscritos, só os aprovados pela organização, ou só os finalistas/premiados pelo júri? | 🟢 **Só os projetos aprovados pela organização** entram na votação (26/09). Ver P43. |
| P38 | **Período** da votação popular: o edital não traz essa data. | 🟢 **Junto com a avaliação dos jurados**: de 29/10 a 20/11/2026 (26/09). |
| P39 | A **contagem de votos** fica visível durante a votação ou só no final? | 🟢 **Oculta até o fim**: o público não vê parciais (26/09). Ver P48. |
| P40 | O resultado do voto popular sai por **categoria** ou é **geral**? | 🟢 **Por categoria**: um mais votado em cada uma (26/09). Ver P49. |
| P48 | A **organização** pode ver a parcial dos votos durante a votação, no painel admin? | 🟢 **Sim**: a organização vê a parcial no painel durante a votação (26/09). |
| P49 | Em caso de **empate** no voto popular de uma categoria, o que acontece: todos os empatados levam, ou existe um critério de desempate? | 🟢 **A organização decide** o desempate, como caso omisso (§8) (26/09). |
| P50 | **Risco do voto popular:** o dígito verificador só confirma que o CPF é matematicamente válido. Existem geradores de CPF válidos na internet, então uma pessoa pode votar várias vezes. Aceitar esse risco ou adicionar alguma proteção (captcha, limite de votos por IP, confirmação de e-mail)? | 🟢 Adicionar **captcha** e **limite por IP** (26/09). Qual captcha e quais números: ver P52. |
| P51 | O **resultado do voto popular** aparece na mesma página pública de premiados? | 🟢 **Sim**: o mais votado de cada categoria aparece **na mesma página** dos premiados pelo júri (26/09). |
| P52 | **Antifraude (P50):** qual serviço de captcha usar (ex.: Cloudflare Turnstile, que é gratuito; Google reCAPTCHA; hCaptcha)? E qual limite por IP (quantos cadastros ou votos por IP em quanto tempo)? Lembrete: redes compartilhadas, como universidade ou empresa, usam um mesmo IP para muitas pessoas. | 🟢 **Cloudflare Turnstile** e limite de **5 cadastros de eleitor por IP por hora** (26/09). |
| P42 | Como validar o **CPF** do eleitor: só o dígito verificador, ou também consulta externa (serviço pago)? O eleitor precisa confirmar o e-mail? E o celular (SMS)? | 🟢 **Só o dígito verificador do CPF**, sem consulta externa, sem confirmação de e-mail e sem SMS (26/09). ⚠️ Risco: ver P50. |
| P43 | Como funciona a **aprovação** do projeto pela organização (P37): é a mesma triagem da inscrição ou uma etapa própria para a votação? | 🟢 **É a mesma triagem da inscrição**: a inscrição aprovada entra na votação popular (26/09). |
| P41 | A organização vai publicar um **adendo ou FAQ** ao edital com as regras que não estão nele? Hoje isso vale para o **voto popular** (P1) e para a restrição **"estudante só na 3.7"** (P9). | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |

## Participante e inscrição

| # | Pergunta | Status |
|---|---|---|
| P3 | Quem é o "participante" dono da conta: pessoa física, pessoa jurídica ou as duas? Em projetos de equipe ou escritório, quem faz a inscrição? | 🟢 **Pessoa física e pessoa jurídica** podem ser titulares da conta (26/09). |
| P4 | Quais dados de **cadastro da conta** coletar? O edital só lista os dados do **projeto** (§4). Exemplos: CPF/CNPJ, telefone, cidade, tipo de participante (§2). | 🟢 Nome, e-mail, senha, **CPF ou CNPJ**, **telefone/WhatsApp**, **cidade/UF** e **tipo de participante** (lista do §2) (26/09). |
| P5 | O formulário deve pedir **campos extras**, como data de conclusão do projeto ou relação com o Ceará? O edital diz que a organização "poderá solicitar" essas informações (§2), mas não diz se elas entram no formulário. | 🟢 **Nenhum campo extra**: o formulário tem só os campos do §4, mais a declaração de IA e os aceites do §8 (26/09). |
| P6 | **Imagens:** quantidade mínima e máxima, formatos e tamanho máximo por arquivo. | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |
| P7 | **Links e vídeos:** só URLs (YouTube, Vimeo etc.) ou também upload de vídeo/PDF? | 🟢 **Links (URLs) + upload de PDF**. Não haverá upload de vídeo (26/09). Limites do PDF: ver P45. |
| P8 | O participante pode **editar** a inscrição depois de enviada, enquanto o prazo estiver aberto? | 🟢 **Sim**: o participante pode editar até o fim do prazo de inscrição, e depois a inscrição trava (26/09). |
| P9 | A categoria **3.7 Estudantes** é exclusiva para estudantes? E estudantes podem se inscrever também nas outras categorias? | 🟢 **A 3.7 é exclusiva para estudantes, e estudantes só podem concorrer na 3.7** (26/09). ⚠️ Essa regra é **mais restritiva que o edital**: o §2 permite estudantes sem limitar a categoria. Ver P41. |
| P10 | O mesmo projeto ou produto pode ser inscrito em **mais de uma categoria** (ex.: 3.2 e 3.9)? | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |
| P11 | Como registrar os **aceites** do §8: um checkbox por item ou um aceite único? É preciso guardar data/hora e IP? | 🟢 **Um checkbox por item** do §8 (26/09). Se é preciso guardar data/hora e IP: ver P53. |
| P12 | Quando a organização pede **comprovação** (§2, §3.7, §4), isso acontece dentro do sistema (upload, status) ou por fora (e-mail)? | 🟢 **Dentro do sistema**: a organização muda o status para *comprovação solicitada* e o participante envia o arquivo na área logada (26/09). Formatos e limites: ver P54. |
| P53 | Nos aceites (P11), é preciso guardar **data/hora e IP** de cada aceite, como comprovação para a LGPD? | 🟢 **Sim**: guardar data/hora e IP de cada aceite (26/09). |
| P54 | **Comprovação (P12):** quais formatos de arquivo são aceitos (PDF, imagem), quantos arquivos, qual o tamanho máximo e se há um prazo para o participante responder. | 🟢 **Só PDF, 1 arquivo, até 10 MB** (26/09). Se há prazo para o participante responder: ver P56. |
| P56 | **Comprovação:** existe prazo para o participante enviar o arquivo depois da solicitação? O que acontece se ele não enviar? | 🟢 **O admin define o prazo** em cada solicitação. Depois do prazo, a organização decide o que fazer (26/09). |
| P45 | **PDF complementar** (P7): quantos por inscrição, qual o tamanho máximo, e se é obrigatório ou opcional. | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |

## Jurados e avaliação

| # | Pergunta | Status |
|---|---|---|
| P13 | **Avaliação cega:** os jurados veem a autoria e os créditos do projeto? | 🟢 **Sim, os jurados veem a autoria e os créditos** (sem avaliação cega) (26/09). |
| P14 | Quantos jurados cada categoria terá? Cada jurado avalia **todos** os projetos da categoria ou um subconjunto? | 🟢 **3 jurados fixos por categoria**, que avaliam todos os projetos dela (26/09). Ver P47. |
| P15 | Quem faz a **atribuição** dos jurados aos projetos: o admin manualmente ou o sistema automaticamente? | 🟢 **O admin atribui manualmente** os jurados de cada projeto (26/09). |
| P16 | Como aparece o **conflito de interesse** (§8): o próprio jurado declara no sistema ou a organização identifica? | 🟢 **Quem identifica o conflito é a organização**, que marca e substitui o jurado. O jurado não declara pelo sistema (26/09). |
| P17 | Como o jurado recebe o acesso: convite por e-mail ou conta criada pela organização? | 🟢 **O admin cria a conta do jurado com senha** e repassa o acesso a ele (26/09). |
| P18 | O jurado pode deixar **comentário ou justificativa** além das notas? Se puder, o participante vê esse texto? | 🟢 **Não.** O jurado só dá as 4 notas de 1 a 5, sem comentário (26/09). |
| P19 | O jurado pode **alterar as notas** depois de enviar? Quem corrige em caso de erro (§8, pedidos de revisão)? | 🟢 **Não.** Depois do envio, as notas travam e só a organização pode corrigir, em caso de erro (§8) (26/09). Ver P46. |
| P20 | Os **pedidos de revisão** (§8) chegam pelo sistema ou por outro canal? | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |
| P46 | Quando a organização corrige uma nota (P19), é preciso registrar **justificativa** e **histórico** (quem alterou, quando, valor anterior)? | 🟢 **Histórico + justificativa obrigatória**: registrar quem alterou, quando, o valor anterior, o valor novo e o motivo (26/09). |
| P47 | Com 3 jurados fixos por categoria (P14), se um deles tiver conflito com um projeto (P16), quem **substitui nesse projeto**: um jurado de outra categoria ou um jurado reserva? Existem jurados reserva? | 🟢 **A organização escolhe** o substituto daquele projeto: um reserva, um jurado de outra categoria ou um convidado novo (26/09). |

## Resultados e premiação

| # | Pergunta | Status |
|---|---|---|
| P21 | Como o **resultado** é divulgado (§7, 21/11): página no site, apenas e-mail, ou evento/redes sociais? O que aparece (projeto, autores, categoria, distinção, nota)? | 🟢 **Página pública** de premiados no site (26/09). O que ela exibe: ver P22 e P44. |
| P22 | As **notas finais** e as individuais podem ser mostradas ao participante ou ao público? | 🟢 O **participante vê a nota final** (0–60) na área logada e o **público vê a nota final** na página de premiados. As notas de cada jurado e de cada critério **não são exibidas** (26/09). |
| P23 | Os **certificados** (§6) são gerados pelo sistema ou produzidos pela organização por fora? | 🟢 **Não.** Os certificados são produzidos pela organização fora do sistema (26/09). |
| P44 | O que a **página pública de resultados** mostra de cada premiado: nome do projeto, autores/créditos, categoria, distinção, imagens? Mostra também a nota? | 🟢 A página mostra: **nome do projeto, categoria, distinção, nota final, autoria e créditos, imagens e os textos da inscrição** (apresentação, contexto/solução, resultados) (26/09). |
| P24 | Haverá uma **galeria pública** dos projetos premiados ou inscritos (memória institucional, §8)? | 🟢 **Sim**: galeria pública com **todos os projetos aprovados** na triagem, que também serve para o voto popular (26/09). O que ela mostra e desde quando: ver P55. |
| P55 | **Galeria (P24):** a partir de quando ela fica visível (início da votação em 29/10, ou antes)? E o que mostra de cada projeto (os mesmos dados da página de premiados, P44)? A nota e a distinção só aparecem depois de 21/11? | 🟢 A galeria fica visível a partir de **29/10**, no início da votação, e mostra só **nome, categoria e imagens** de cada projeto (26/09). Ver P57. |
| P57 | Depois da divulgação (21/11), a **galeria** continua no ar? Ela passa a mostrar a distinção e a nota, ou isso fica só na página de premiados? | 🟢 **A galeria continua no ar** depois de 21/11 e passa a mostrar também a **distinção e a nota final** de cada projeto (26/09). |
| P58 | Um **participante** (ou jurado) pode votar no voto popular usando a mesma conta? Um participante pode votar no **próprio projeto**? | 🟢 **Qualquer CPF vota**, sem restrição: participantes, jurados e o próprio autor podem votar, 1 vez por categoria (26/09). Ver P60. |
| P60 | Quem já tem conta (participante PF ou jurado) vota **com a mesma conta**, ou precisa de um cadastro de eleitor separado? E um participante **PJ** (que tem CNPJ, não CPF) precisa de um cadastro de eleitor com CPF? | 🟢 **Cadastro de eleitor separado**: quem quiser votar, inclusive participante ou jurado, cria um cadastro de eleitor próprio com CPF (26/09). |
| P25 | Quais **e-mails automáticos** o sistema deve enviar (confirmação de inscrição, lembrete aos jurados, resultado)? | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). Observação: a **recuperação de senha** (Fortify, T1) já exige envio de e-mail, então o serviço de e-mail (P31) é necessário de qualquer forma. |

## Site público e conteúdo

| # | Pergunta | Status |
|---|---|---|
| P26 | Quais **seções** o site deve ter? A referência (Bornancini) tem: hero, inscrição, categorias, números da edição anterior, patrocinadores, diretoria, CTA e privacidade. | 🟢 Seções: **Hero + Sobre o prêmio**, **Categorias + Avaliação**, **Datas + Como participar**, **Parceiros + Diretoria** e **Avaliadores** (jurados) (26/09). Detalhes dos avaliadores: ver P59. |
| P59 | **Seção Avaliadores:** o que mostrar de cada jurado (nome, foto, mini bio, categoria)? A seção fica visível desde o lançamento ou só a partir de uma data? | 🟢 Cada avaliador aparece com **nome, foto e mini bio** (26/09). A partir de quando a seção fica visível: ver P61. |
| P61 | A seção **Avaliadores** fica visível desde o lançamento do site, ou só a partir de uma data definida pela organização? | 🟢 **Desde o lançamento** do site (26/09). |
| P27 | Quem fornece o **conteúdo** (textos, logo em vetor, paleta, fontes, imagens das categorias, logos dos parceiros)? | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |
| P28 | Existe **política de privacidade** e um responsável/contato de LGPD da Associação? | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |

## Infraestrutura

| # | Pergunta | Status |
|---|---|---|
| P29 | Qual é o **domínio** (ex.: subdomínio de cearadesign.org.br)? | 🟢 **Já existe domínio.** A votação ficará em um **subdomínio** (26/09). Qual domínio e subdomínio: ver P62. |
| P30 | Onde será a **hospedagem**? Já existe servidor ou conta? Qual é o orçamento mensal? | 🟢 **Já existe servidor na HostGator** (26/09). Qual plano: ver P63. ⚠️ Conflito com o PostgreSQL: ver P64. |
| P31 | Qual serviço de **e-mail transacional** usar? Já existe algum? | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |
| P32 | Quem **mantém** o sistema depois do prêmio? Ele deve servir para as próximas edições (multi-edição)? | 🟢 **Não**: o sistema atende só a edição 2026 (26/09). As datas continuam editáveis no admin, porque o edital permite alterações (§7). |
| P33 | Vocês preferem **MySQL ou PostgreSQL**? A máquina local tem MySQL 9.6. | 🟢 ~~PostgreSQL~~ → **MySQL** (26/09). Mudou por causa da hospedagem compartilhada da HostGator (P63, P64). |
| P62 | Qual é o **domínio** e o **subdomínio** exato do sistema (ex.: `premio.cearadesign.org.br`)? O site público do prêmio fica no mesmo subdomínio da votação/inscrição? | 🟡 Aguardando a organização (o responsável vai confirmar, 26/09). |
| P63 | Qual o **tipo de plano** na HostGator: compartilhada (Start, P, M, Turbo, Business, Plus), VPS ou Dedicado? O plano tem **SSH** habilitado? | 🟢 **Hospedagem compartilhada** (26/09). Se o SSH está habilitado: ver P66. |
| P64 | **Conflito:** o PostgreSQL (P33) **não é suportado na hospedagem compartilhada** da HostGator, só em VPS ou Dedicado (tabela de compatibilidade da HostGator). Se o plano for compartilhado, é preciso usar **MySQL** (a HostGator oferece MySQL 5.7 ou 8.0, conforme o servidor) ou mudar de plano. | 🟢 **Usar MySQL** (26/09). |
| P65 | Qual **versão do MySQL** está no servidor da HostGator: 5.7 (servidores CentOS) ou 8.0 (AlmaLinux)? Aparece no cPanel, em "Informações do servidor" ou no phpMyAdmin. Isso importa porque o MySQL 5.7 **ignora** restrições `CHECK` (elas só são aplicadas a partir do 8.0.16). Nesse caso, a regra "nota de 1 a 5" fica garantida só pela aplicação. | 🟡 O responsável vai verificar no cPanel (26/09). Até lá, o código não depende de `CHECK` no banco: a regra de 1 a 5 é validada na aplicação. |
| P66 | O plano compartilhado tem **acesso SSH habilitado**? Sem SSH, não dá para rodar Composer nem `php artisan` (migrations) no servidor, e o deploy muda: o `vendor/` teria que ser enviado pronto e as migrations rodadas de outra forma. | 🟢 **Não há SSH** no plano (26/09). O deploy será sem SSH; ver a seção "Deploy sem SSH" em 02-stack.md e P67. |
| P67 | Dá para **pedir ao suporte da HostGator que libere o SSH** no plano? O artigo de instalação do Laravel da HostGator fala em "possuir acesso SSH liberado no plano", o que sugere que pode ser habilitado. Com SSH, o deploy fica bem mais simples e seguro. | 🟢 **Não** vão pedir o SSH (26/09). O deploy será sempre sem SSH. |
| P68 | No cPanel, dá para apontar a **raiz do subdomínio** para a pasta `public/` do Laravel? Se não der, é preciso um ajuste na estrutura de pastas para não expor `.env` e o código. | 🟡 O responsável vai verificar no cPanel (26/09). |

## Regras levantadas ao detalhar o modelo de dados

| # | Pergunta | Status |
|---|---|---|
| P69 | O resultado é publicado por **botão do admin** ou **automaticamente na data**? | 🟢 **Botão do admin**: a organização confere e publica (26/09). |
| P70 | Quando acontece a **triagem**? O que acontece se o participante editar uma inscrição já aprovada? | 🟢 A triagem pode acontecer **durante o período de inscrições**, e **a inscrição aprovada fica travada**: o participante não edita mais (26/09). Isso refina o P8. |
| P71 | O eleitor pode **trocar o voto** dentro de uma categoria? | 🟢 **Não**: o voto é final (26/09). |
| P72 | Se a organização pede comprovação de um projeto **já aprovado**, ele sai da galeria, da votação ou da avaliação? | 🟢 **Não**: continua normalmente, e a organização decide depois (26/09). |
| P73 | O participante precisa **confirmar o e-mail** antes de inscrever projetos? | 🟢 **Não** (26/09). |
| P74 | O **CPF/CNPJ do participante** deve ser único, ou seja, uma conta por documento? | 🟢 **Sim, único**: um CPF/CNPJ só pode ter uma conta de participante (26/09). |
| P75 | Existem **limites** de caracteres nos textos e na quantidade de links? | 🟢 **Sem limite rígido**: só um limite técnico alto. Referência escolhida: **5.000 caracteres por texto** e **5 links** (26/09). |
| P76 | Os jurados avaliam **só as inscrições aprovadas** na triagem? | 🟢 **Sim, só as aprovadas** (26/09). |
| P77 | Qual o **horário de encerramento** das inscrições em 28/10 (o edital só informa a data)? O sistema assume 23:59:59 (horário de Fortaleza) até a confirmação. | 🟢 **28/10/2026 às 23:59, horário de Fortaleza** (26/09). |
| P78 | Se um projeto aprovado for **desclassificado durante a votação popular**, o que acontece com os votos que ele já recebeu? Os eleitores podem votar de novo naquela categoria? (Recomendação atual: manter os votos no banco, desconsiderar na apuração e não liberar novo voto.) | 🟢 **Os votos são descartados na apuração** (ficam no banco) e o eleitor **não vota de novo** naquela categoria (26/09). |
| P79 | O sistema aceita a **comprovação enviada depois do prazo**? (Recomendação atual: aceitar e marcar como atrasada; a organização decide.) | 🟢 **Aceita e marca como atrasada**. A organização decide (26/09). |
| P80 | Se uma nota for **corrigida depois da publicação** do resultado, é preciso avisar o participante? | 🟢 **Não**: a nota e a distinção só são atualizadas na página e na área logada. Se quiser, a organização comunica por fora (26/09). |

## Decisões técnicas pendentes (minhas, para validar)

| # | Pergunta | Status |
|---|---|---|
| T1 | Auth do participante: usar os **painéis Filament** (que já têm cadastro, login, reset e verificação de e-mail) ou páginas próprias com **Fortify** no site? Recomendo o Filament, que é mais rápido. | 🟢 **Páginas próprias com Fortify**, com a identidade visual do site, para participante e eleitor. O Filament fica só para admin e jurado (26/09). |
| T2 | Papéis: `spatie/laravel-permission` + Filament Shield, ou um campo simples `role` em `users`? A resposta depende de P15–P19. | 🟢 **Um campo `role` simples em `users`** (admin, jurado, participante, eleitor) + Policies. Sem spatie/permission e sem Shield (26/09). |
| T4 | O cadastro de eleitor é separado (P60). A mesma pessoa pode ter conta de participante e de eleitor com o **mesmo e-mail**? Se sim, recomendo guardar eleitores em uma tabela própria (`voters`, com CPF e e-mail únicos) em vez de `users`. Se não, a pessoa usa outro e-mail para votar. | 🟢 **Não**: fica tudo em `users`, e quem já tem conta de participante ou jurado usa **outro e-mail** para o cadastro de eleitor (26/09). |
| T3 | A versão 5 do `spatie/laravel-activitylog` exige **PHP 8.4**, e a máquina local tem 8.3.33. Atualizar o PHP para 8.4 (recomendado) ou usar a v4 do pacote? | 🟢 **Atualizar para PHP 8.4** (local e hospedagem) (26/09). |
