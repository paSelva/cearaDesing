# SPEC-002 — Cadastro e login do participante

Status: implementação DEV concluída, aguardando revisão independente do QA. Base: `aba3f29` (`develop`, inclui SPEC-001). Branch: `feature/spec-002-cadastro-participante`.

## Objetivo e escopo

Permitir que pessoas físicas e jurídicas criem uma conta de participante, entrem, saiam e recuperem a senha em páginas próprias do site (Fortify), e acessem uma área do participante mínima. É a porta de entrada para o fluxo de inscrição (SPEC-003).

**Fora do escopo:** recuperação pública de senha para jurado e admin (admin: CLI; jurado: o admin troca a senha no painel, P82); mini bio e foto do jurado (P59); inscrição de projetos; edição do perfil (`UpdateParticipantProfile`, que depende da regra da 3.7 — P9 — e entra com a inscrição); confirmação de e-mail (P73); 2FA e passkeys; alteração de senha logado; cadastro de eleitor e Turnstile (P52, voto popular); envio real de e-mail em produção (⚠️ P31).

### Critérios verificáveis

1. `/register` cria `users` com papel `participante` + `participant_profiles`, e o usuário entra logado em `/participante` sem confirmar e-mail (P73).
2. O papel nunca vem da entrada: um POST com `role=admin` cria participante.
3. CPF é exigido para PF e CNPJ para PJ, validados pelo dígito verificador; o CNPJ aceita os formatos numérico e alfanumérico (P4, P81). Máscara é aceita e removida antes de gravar.
4. Documento único entre participantes, com ou sem máscara (P74); e-mail único em `users`.
5. `participant_type` só aceita a lista do §2; UF só aceita as 27 UFs; telefone com DDD.
6. `/login` aceita somente participante. Admin, jurado e eleitor recebem a mesma mensagem genérica de credencial inválida (não revela o papel).
7. Login limitado a 5 tentativas por minuto por e-mail + IP (429 na 6ª).
8. Recuperação de senha envia link e redefine só para participante. E-mail não cadastrado, e-mail de admin/jurado e pedido repetido (throttle) recebem a mesma resposta de sucesso (não revela quais e-mails têm conta nem o papel); admin/jurado não recebem link e não redefinem por aqui, mesmo com token válido.
9. `/participante`: visitante vai para o login; outros papéis recebem 403; a página não mostra documento nem telefone.
10. Mensagens de validação, login e senha em português.
11. Em `/admin/jurados`, só o admin lista e cria jurados (nome, e-mail, senha de 12+ caracteres, P17) e define uma nova senha para um jurado (P82). A troca invalida o "manter conectado". Ninguém exclui contas; o admin não altera outros admins pelo painel.

## Regras, decisões e perguntas

- **Edital:** tipos de participante (§2); dados pessoais usados só para o prêmio (§8).
- **Decisões confirmadas:** PF e PJ podem ser titulares (P3); dados do cadastro (P4); sem confirmação de e-mail (P73); documento único (P74); páginas próprias com Fortify para participante e eleitor (T1); tudo em `users`, com e-mail distinto para cadastro de eleitor (T4).
- **Nova decisão (P82, 28/09):** o jurado não usa a recuperação pública; o admin define a nova senha no painel.
- **Nova decisão (P81, 27/09):** aceitar CNPJ alfanumérico (IN RFB 2.229/2024), com o dígito verificador oficial (caractere = código ASCII − 48). Registrada em `docs/06` e `docs/04`.
- **Recomendações técnicas aplicadas:**
  - senha com mínimo de 8 caracteres (o admin, criado por CLI, continua com 12);
  - telefone gravado só com dígitos, com DDD (10 ou 11 dígitos);
  - e-mail em minúsculas; UF em maiúsculas;
  - `Password::uncompromised()` **não** usado: depende de serviço externo (a saída de rede da HostGator não foi verificada).
- **Leitura adotada:** o §7 define o período de **inscrições de projetos**, não de contas. O cadastro de conta não é bloqueado por data.
- **Divergência documentada:** `docs/07` prevê `BusinessRuleException`; esta SPEC usa `ValidationException` por campo, como as Actions da SPEC-001 e como o Fortify espera para devolver os erros ao formulário.
- **Não inventado (fica para P#):** nenhuma restrição entre tipo de pessoa e tipo de participante (ex.: PJ + estudante). Se a organização quiser restringir, é uma pergunta nova.
- **Alteração de teste da SPEC-001:** o teste que exigia `/register` inexistente foi substituído por um mais forte — o cadastro público existe (T1), mas um POST com `role=admin` cria participante.

## Dados e operações

- Migração `create_participant_profiles_table`, conforme `docs/04`: `user_id` único (FK restritiva), `person_type` (`pf`/`pj`), `document` varchar(14) único, `phone`, `city`, `state`, `participant_type` (seis valores do §2).
- Enums PHP `PersonType` e `ParticipantType`, com rótulos em português.
- Regras `App\Rules\Cpf` e `App\Rules\Cnpj` (normalização + dígito verificador), previstas em `docs/07`.
- Action `App\Actions\Account\RegisterParticipant`: normaliza, valida, cria usuário e perfil em uma transação e converte `UniqueConstraintViolationException` (corrida entre requisições) em erro de campo.
- Fortify 1.40 (compatível com Laravel 13): só `registration` e `resetPasswords`. Migrations de 2FA/passkeys publicadas pelo `fortify:install` foram removidas; Actions de edição de perfil e senha não foram mantidas. Confirmação de senha responde 404.
- `Role::siteAuth()` define os papéis da autenticação do site (hoje só `participante`; o eleitor entra na SPEC do voto popular). É usada no `Fortify::authenticateUsing`, no envio do link de recuperação (`User::sendPasswordResetNotification`) e no `ResetUserPassword`.
- Resposta neutra na recuperação de senha (`NeutralPasswordResetLinkResponse`).
- Middleware `role:<papel>` para grupos de rotas; não substitui Policies.
- Actions `CreateJudge` (parcial: sem bio/foto) e `SetJudgePassword`; `UserPolicy` (admin gerencia só jurados, ninguém exclui); Resource Filament `JudgeResource` em `/admin/jurados` com a ação "Alterar senha".
- Traduções `lang/pt_BR` (`validation`, `auth`, `passwords`, `pagination`).
- `pint.json` exclui `skills/` (exemplos PHP de documentação, não código da aplicação).

## Acesso

| Recurso | Visitante | Participante | Admin/jurado/eleitor |
|---|---|---|---|
| `/register`, `/login`, `/forgot-password` | permite | redireciona para `/participante` | — |
| `POST /login` | só credencial de participante | — | recusado com mensagem genérica |
| `/participante` | redireciona para `/login` | permite | 403 |
| `/admin` | inalterado (SPEC-001) | 403 | admin |

## Pendências e riscos

- **P2 (urgente):** as inscrições abrem em 28/09/2026 e esta SPEC não recebe inscrições.
- **P31:** sem provedor de e-mail, a recuperação de senha não funciona em produção.
- **Cadastro sem captcha:** o Turnstile foi decidido só para o eleitor (P52). O cadastro de participante não tem limite por IP além do padrão; se houver abuso, é decisão nova.
- **Deploy:** HostGator, P62, P65 e P68 continuam não verificados.
- **Ambiente:** `phpunit.xml` fixa o banco `ceara_spec001_testing`; um bootstrap com outro `DB_TEST_DATABASE` falha nos testes até o banco ser criado à mão (ver handoff).

## Evidência DEV

[`docs/qa/SPEC-002-handoff.md`](../qa/SPEC-002-handoff.md).
