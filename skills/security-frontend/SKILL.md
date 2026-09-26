---
name: security-frontend
description: >
  Auditoria e hardening de segurança da camada de interface do Prêmio Ceará Tem Design:
  views Blade, componentes Livewire 4 (área do participante e voto popular), painéis
  Filament 5 (/admin e /jurado), Alpine.js, Tailwind e assets compilados pelo Vite.
  Identifica vulnerabilidades reais, gera correções prontas e pesquisa referências quando
  encontrar padrão desconhecido. Use este skill sempre que o usuário mencionar:
  "segurança do frontend", "XSS", "{!! !!}", "Blade seguro", "escape", "Livewire seguro",
  "propriedade pública", "#[Locked]", "wire:model", "Filament seguro", "canAccessPanel",
  "CSP", "Content Security Policy", "CSRF", "@csrf", "clickjacking", "iframe", "link
  malicioso", "javascript: no href", "Turnstile", "captcha", "upload na tela", "dados
  expostos na página", "galeria pública", "npm audit", "Vite", "assets", ou qualquer variação.
  Acione também quando o usuário mostrar arquivos .blade.php, componentes Livewire,
  Resources/Pages do Filament, JS/Alpine, ou disser "antes de ir pra produção".
---

# Security:Frontend — Blade + Livewire + Filament (Prêmio Ceará Tem Design)

Não há SPA nem API separada (docs/02): a interface é renderizada no servidor. Os riscos
principais são **XSS em Blade**, **adulteração de estado do Livewire** e **acesso indevido
aos painéis Filament**.

> **Regra do projeto:** o que aparece em cada tela pública foi decidido (P44, P55, P57, P59).
> Não exibir dado além disso sem confirmação.

---

## Passo 0 — Reconhecimento Inicial

1. Ler `references/cearadesign-context.md` (superfícies, o que é público).
2. Código disponível? `resources/views`, `app/Livewire`, `app/Filament`, `resources/js` → analisar.
3. Padrão desconhecido (pacote JS, plugin Filament, diretiva) → pesquisar antes de opinar.

---

## Passo 1 — XSS (Cross-Site Scripting)

### 1.1 Blade: `{{ }}` × `{!! !!}`

`{{ }}` escapa com `htmlspecialchars`. `{!! !!}` **não** escapa.

```blade
{{-- ❌ CRÍTICO — descrição do projeto vem do participante --}}
{!! $entry->description !!}

{{-- ✅ texto puro com quebras de linha --}}
{!! nl2br(e($entry->description)) !!}
{{-- ou --}}
<p class="whitespace-pre-line">{{ $entry->description }}</p>
```

- [ ] `grep -rn "{!!" resources/views` — cada ocorrência justificada
- [ ] Textos de participante, bio de jurado (P59), nomes de parceiros/diretoria: sempre `{{ }}`
- [ ] Se algum campo precisar de HTML rico: sanitizar no servidor com allowlist (pacote de purificação — ⚠️ nenhum escolhido em docs/02)

### 1.2 URLs vindas do usuário (href/src)

```blade
{{-- ❌ javascript:alert(1) passa pelo escape de HTML --}}
<a href="{{ $link->url }}">Ver projeto</a>
```

- [ ] Validar na gravação: `['url:http,https']`
- [ ] Links externos com `rel="noopener noreferrer"` e `target="_blank"` quando abrir em nova aba

### 1.3 Dados em JavaScript/Alpine

```blade
{{-- ❌ quebra de contexto --}}
<div x-data="{ title: '{{ $entry->title }}' }">

{{-- ✅ --}}
<div x-data="{ title: @js($entry->title) }">
```

- [ ] `@js()` / `Js::from()` para passar dados ao Alpine/JS
- [ ] Nada de `x-html` com dado de usuário

---

## Passo 2 — Livewire

Propriedades públicas são enviadas ao navegador e **podem ser alteradas** pelo cliente.

- [ ] IDs e flags que o usuário não pode mudar: `#[Locked]`
- [ ] Todo método público chamável faz `$this->authorize()` + validação
- [ ] Não expor model completo em propriedade pública se ele tem campos sensíveis (CPF, telefone)
- [ ] Nada de regra de negócio só no front (botão desabilitado) — a Action revalida (docs/03)
- [ ] Uploads temporários: validação no `rules()` **e** em `config/livewire.php` (`temporary_file_upload`)

```php
#[Locked]
public int $categoryId;

public function vote(CastPopularVote $action, int $entryId): void   // docs/07
{
    $entry = Entry::findOrFail($entryId);
    $this->authorize('vote', $entry);      // eleitor, aprovada (P37), período (P38)
    $action->handle(auth()->user(), $entry);
}
```

---

## Passo 3 — Filament (`/admin` e `/jurado`)

- [ ] `User implements FilamentUser` + `canAccessPanel()` por papel
- [ ] Resource sem Policy = liberado para qualquer um que entre no painel → toda Resource com Policy
- [ ] Painel do jurado: listagem escopada às atribuições dele; Actions de edição de nota escondidas **e** bloqueadas após envio (P19)
- [ ] Colunas/Infolists não exibem CPF/telefone onde não é necessário
- [ ] Colunas com `->html()` ou `->markdown()` só com conteúdo confiável
- [ ] Exportações (se forem confirmadas — ainda não perguntado, docs/02) com autorização

---

## Passo 4 — Formulários e CSRF

- [ ] `@csrf` em todo `<form method="POST">` Blade (Livewire e Filament cuidam do próprio)
- [ ] `@method('PUT'/'DELETE')` para verbos não-POST
- [ ] Mensagens de erro de login genéricas (não revelar se o e-mail existe)
- [ ] Turnstile no cadastro de eleitor (P52): widget no front **e** validação no servidor (o widget sozinho não protege)

---

## Passo 5 — Content Security Policy (CSP)

Livewire, Alpine e Filament usam scripts/estilos inline; o Turnstile carrega de
`challenges.cloudflare.com`. Estratégia:

1. Começar com `Content-Security-Policy-Report-Only`
2. Observar violações no console
3. Ativar a política ajustada

> Política inicial sugerida: `references/patterns.md`.

---

## Passo 6 — Clickjacking e iframes

- [ ] `X-Frame-Options: SAMEORIGIN` ou `frame-ancestors 'self'` (middleware — `security-audit/references/laravel-hardening.md`)
- [ ] Páginas de login, voto e painéis nunca embutíveis por outros domínios
- [ ] Embeds de terceiros (vídeo de projeto, se houver) com `sandbox` quando possível

---

## Passo 7 — Uploads na interface

- [ ] `accept=` no input é só conveniência — a validação real é no servidor (skill `security-backend`, Passo 9)
- [ ] Preview de imagem usando URL temporária do Livewire, não caminho de disco
- [ ] Comprovações (PDF, P54) nunca listadas em página pública

---

## Passo 8 — Dados expostos nas páginas públicas

| Página | Pode exibir | Fonte |
|---|---|---|
| Galeria (desde 29/10) | nome, categoria, imagens | P55 |
| Galeria (após 21/11) | + distinção e nota final | P57 |
| Resultados | nome, categoria, distinção, nota final, autoria e créditos, imagens e textos | P44 |
| Avaliadores | nome, foto, mini bio | P59, P61 |
| Contagem de votos | **oculta** até publicar | P39 |

- [ ] View-source e respostas do Livewire não carregam campos além dessa lista
- [ ] Resultado só depois do botão **Publicar** (P69) — checagem no servidor, não `@if` escondendo HTML já renderizado

---

## Passo 9 — Dependências de front (npm)

```bash
npm audit --audit-level=high
npm outdated
```

- [ ] `package-lock.json` commitado
- [ ] Só assets compilados (`public/build`) vão para o servidor; `node_modules` nunca
- [ ] Scripts de terceiros (além do Turnstile) justificados; se via CDN, com SRI

---

## Passo 10 — Aprendizado com Código Desconhecido

1. Pesquisar `"[pacote/diretiva] security"`, `"[plugin] filament security"`, CVEs
2. Conferir compatibilidade com Filament 5 / Livewire 4
3. Registrar no relatório

---

## Passo 11 — Relatório Final

```markdown
# 🛡️ Relatório de Segurança — Frontend
**Projeto:** Prêmio Ceará Tem Design 2026
**Data:** [data]
**Stack:** Blade · Livewire [versão] · Filament [versão] · Alpine · Tailwind · Vite

## Resumo Executivo

## Achados por Severidade
### 🔴 CRÍTICO — Corrigir antes de produção
### 🟠 ALTO — Corrigir em < 1 semana
### 🟡 MÉDIO — Próximo ciclo
### 🟢 BAIXO / Informativo

## ⚠️ Dependem de decisão (P#)

## Plano de Ação

## O que está BEM ✅

## Novos padrões aprendidos
```

---

## Referências

- `references/patterns.md` — padrões seguros de Blade, Livewire, Filament e CSP
- `references/cearadesign-context.md` — contexto do projeto relevante para segurança
