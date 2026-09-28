# SPEC-009 — Cadastro de eleitor (voto popular)

Status: implementação DEV concluída, aguardando revisão. Base: `feature/site-publico`. Branch: `feature/spec-009-eleitor`.

## Objetivo e escopo

Quem quiser votar no voto popular (P1) cria um cadastro de eleitor, separado da conta de participante (P60), com CPF (P35) validado só pelo dígito verificador (P42), protegido por Cloudflare Turnstile e limite de 5 cadastros por IP por hora (P50, P52).

**Fora do escopo (SPEC-010):** galeria, voto, parcial para o admin, apuração e desempate. **Fora (sem decisão):** o que o voto popular premia e se entra na nota (⚠️ P34); adendo ao edital (⚠️ P41).

### Critérios verificáveis

1. `/eleitor/cadastro` pede nome, e-mail, CPF, senha e a verificação do Turnstile; cria `users` com papel `eleitor` + `voter_profiles`, entra logado e vai para `/eleitor`. Sem confirmação de e-mail (P42).
2. Um cadastro por CPF, com ou sem máscara (P35). O CPF é guardado como `cpf_hash` (HMAC-SHA256, único) e `cpf_encrypted`; nunca em claro.
3. O e-mail é único em `users`: quem já tem conta de participante ou de jurado usa outro e-mail (T4, P60); a mensagem explica isso. O mesmo CPF pode ter conta de participante PF e cadastro de eleitor (P60).
4. Turnstile validado no servidor; falha fechada se não houver chave secreta, se o token faltar, for inválido ou se a Cloudflare não responder.
5. No máximo 5 cadastros **concluídos** por IP por hora (P52); tentativas com erro não contam.
6. Sem `CPF_HMAC_KEY`, o cadastro fica indisponível (não gera hash com chave vazia).
7. O eleitor entra pelo login do site (`/login`) e vai para `/eleitor`; não acessa `/participante`. O participante continua indo para `/participante`. A recuperação de senha passa a atender eleitores.

## Regras e decisões

- **Decisões confirmadas:** P35, P42, P50, P52, P58, P60, T1, T4.
- **Leitura adotada:** o limite por IP conta cadastros concluídos (P52 fala em "cadastros").
- **Alteração de teste da SPEC-002:** o teste que recusava o login do eleitor passou a recusar só admin e jurado.

## Dados e operações

- Migration `create_voter_profiles_table` conforme `docs/04`.
- `VoterProfile` (sem atribuição em massa; hash, CPF e IP ocultos na serialização).
- Action `App\Actions\Voting\RegisterVoter`; regra `App\Rules\Turnstile`; `config/services.php` (`turnstile`, `cpf_hmac`).
- `RoleHomeLoginResponse`: redireciona o login do site por papel.
- `Role::siteAuth()` inclui `eleitor`.

## Pendências e riscos (antes de publicar)

- **Chaves da Cloudflare:** a organização precisa de uma conta Cloudflare e de um widget Turnstile para o subdomínio de produção (⚠️ P62). Sem as chaves, o cadastro de eleitor fica indisponível.
- **`CPF_HMAC_KEY`:** gerar uma vez no deploy e nunca trocar. Trocar `APP_KEY` quebra `cpf_encrypted` (usar `APP_PREVIOUS_KEYS`).
- **IP atrás de proxy:** se houver proxy/Cloudflare na frente da HostGator, `$request->ip()` pode ser o mesmo para todos e bloquear o cadastro depois de 5 pessoas. Conferir no deploy e confiar só nos proxies conhecidos (afeta P52 e P53).
- **P28:** política de privacidade ainda pendente; o formulário explica para que o CPF é usado.
- Nada está no ar (P2, P62, P65, P68).
