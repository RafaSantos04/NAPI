# Fase 4.2 — Administração de Usuários + Admin Authorization

[← Fases](README.md) · [Autorização](../security/authorization.md#área-administrativa-blade) · [ADR-0009](../architecture/decisions/ADR-0009-web-session-and-bearer-adapters.md) · [Fase 4.1](phase-04-1-admin-shell.md)

**Commit:** pending

## Objetivo

Primeira funcionalidade administrativa real: listar, buscar, ver, criar,
editar, ativar, desativar e atribuir perfis a usuários pela área Blade. A
fase também precisava provar que o modelo das Fases 3 e 3.2 aceita um segundo
adapter de entrada com as mesmas regras, sem duplicá-las.

```text
REST API (Bearer)  ┐
                   ├── Policies (hasPermission) → Actions → invariantes → eventos → audit_logs
Blade (sessão web) ┘
```

## Itens de transição

| Item | Resultado |
|---|---|
| Laravel Boost | `laravel/boost ^2.10` em `require-dev` (o pacote já estava no `composer.json`, com `boost.json` configurado só para o agente `antigravity`). `boost:install` rodou para `antigravity` e `claude_code`: guidelines em `AGENTS.md`/`CLAUDE.md`, skills em `.agents/skills` e `.claude/skills`, MCP em `.mcp.json` e `.agents/mcp_config.json`. Esses arquivos, o `boost.json` e o `.ai/` ficam só na máquina local (`.gitignore`); o repositório guarda apenas a dependência em `composer.json`/`composer.lock`. Os dois arquivos de guidelines continham só o bloco de bootstrap do Boost, sem regras do NAPI. Foi acrescentado, fora do bloco gerenciado, um bloco "NAPI — regras do projeto" que tem precedência sobre o do Boost. Ele cobre duas diferenças: comentários inline do projeto em vez de PHPDoc, e documentação como parte de cada fase. Nada da aplicação depende do Boost. `composer validate` passou |
| Acesso ao shell | restrito por permissão funcional, ver [abaixo](#acesso-à-área-administrativa) |
| Status da Fase 3.2 | `docs/phases/README.md`, a própria fase e os findings deixaram de dizer `pending`. Os hashes vêm do `git log` (`2392411`…`dc5066a`). A 4.1 também (`a30deac`…`ea89fad`) |

## Acesso à área administrativa

Não foi criada flag, permission key nem lista de e-mails. A regra deriva do
modelo existente:

```text
AdminNavigation (seções)          Usuários → UserPolicy::viewAny → hasPermission('users.view')
        ↓
entra quem vê ≥ 1 seção           admin: sim · dev/viewer: não · perfil com users.view: sim
```

- **Por que não `admin.access`.** Uma permission key nova precisaria de uma
  linha de menu, de matriz e de concessão manual, e repetiria uma informação
  que as permissões das áreas já dão. "Ter uma permissão que a área consegue
  usar" é a regra coerente: permissões de áreas sem tela (por exemplo, só
  `menus.*`) não dão entrada.
- **Login.** Com credenciais válidas sem acesso, a sessão não é aberta e a
  resposta é `Esta conta não tem acesso à área administrativa.`. O login de
  API já confirma as mesmas credenciais, então isso não cria sinal novo de
  enumeração.
- **Sessão existente.** `EnsureUserCanAccessAdmin` (`admin.access`) encerra a
  sessão de quem perdeu o acesso e redireciona para `/admin`, igual ao
  `EnsureUserIsActive`. O comportamento é o de um visitante, então nada sobre
  as rotas internas é revelado.
- **Dentro da área**, a convenção da API não mudou: permissão funcional
  ausente → 404, regra contextual → 403, invariante → mensagem de conflito.

## Rotas

| Método | URI | Nome | Middleware | Ação |
|---|---|---|---|---|
| GET | `/admin` | `admin.home` | `admin.access` | landing (visitante) ou shell |
| POST | `/admin/login` | `admin.login` | `guest` | login por sessão |
| POST | `/admin/logout` | `admin.logout` | `auth` | logout |
| GET | `/admin/users` | `admin.users.index` | `auth`, `admin.access`, `permission:users.view` | listagem, busca, paginação |
| GET | `/admin/users/create` | `admin.users.create` | … `permission:users.create` | formulário |
| POST | `/admin/users` | `admin.users.store` | … `permission:users.create` | `CreateUser` |
| GET | `/admin/users/{user}` | `admin.users.show` | … `permission:users.view` | detalhe + perfis |
| GET | `/admin/users/{user}/edit` | `admin.users.edit` | … `permission:users.update` | formulário |
| PUT | `/admin/users/{user}` | `admin.users.update` | … `permission:users.update` | `UpdateUser` |
| DELETE | `/admin/users/{user}` | `admin.users.destroy` | … `permission:users.delete` | `DeleteUser` |
| POST | `/admin/users/{user}/deactivate` | `admin.users.deactivate` | … `permission:users.update` | `DeactivateUser` |
| POST | `/admin/users/{user}/activate` | `admin.users.activate` | … `permission:users.update` | `ActivateUser` |
| PUT | `/admin/users/{user}/profiles` | `admin.users.profiles.update` | … `permission:users.update` | `AssignProfilesToUser` |

O admin oferece DELETE /admin/users/{user} (admin.users.destroy), com users.delete, UserPolicy::delete e DeleteUser. A exclusão lógica preserva histórico. Na
API, `POST /api/v1/users/{user}/deactivate|activate` foram adicionadas com
as mesmas Actions, o endpoint herdado da 3.2.

## Autorização

| Quem | Regra |
|---|---|
| Entra no admin | quem vê ao menos uma seção (`AdminNavigation`). Hoje: `users.view` |
| Lista usuários | `users.view` (`permission:` + `UserPolicy::viewAny`) |
| Vê um usuário | `users.view` (`UserPolicy::view`) |
| Cria | `users.create` (`UserPolicy::create`) |
| Altera nome/e-mail | `users.update`; só admin altera conta de administrador (`UserPolicy::update`) |
| Desativa | `users.update`; nunca a si mesmo; só admin desativa administrador (`UserPolicy::deactivate`); invariante do último admin (`DeactivateUser`) |
| Reativa | `users.update`; só admin reativa administrador (`UserPolicy::activate`) |
| Atribui perfis | `users.update` + `UserPolicy::assignProfiles` (anti-escalação da 3.2, intacta) + invariante (`AssignProfilesToUser`) |

`admin` continua superusuário **funcional** apenas: não ignora Policy (não
se desativa) nem invariante (não remove o próprio perfil se for o último).
`dev` continua só com autoatendimento: não entra no admin e não lista
usuários por nenhum canal.

## Arquitetura

```text
Browser → web (sessão, CSRF, EnsureUserIsActive)
        → auth → admin.access → permission:users.* (404)
        → Web\Admin controller
             → FormRequest (estende o da API: mesmas regras e authorize() → Policy, 403)
             → DTO (CreateUserDto / UpdateUserDto / AssignProfilesToUserDto)
             → Action (DB::transaction → invariante → estado → evento Auditable)
        → redirect + flash (status / error)
```

| Decisão | Motivo |
|---|---|
| `CreateUser`/`UpdateUser` extraídas do `UserController` da API | havia duplicação real entre os dois adapters (transação, evento, cálculo de campos alterados); o DTO fixa os campos aceitos ([ADR-0007](../architecture/decisions/ADR-0007-dto-action-boundary.md#evolução-na-fase-42)) |
| `DeactivateUser`/`ActivateUser` | regra de negócio explícita; nunca `update(['is_active' => …])` no controller |
| FormRequests web **estendem** os da API | regras e `authorize()` num único lugar; a web acrescenta só mensagens em pt-BR. `UpdateUserRequest` troca `sometimes` por `required` (o formulário sempre envia tudo), e `AssignProfilesRequest` trata o formulário sem checkbox como `[]` antes do `authorize()` |
| `CheckPermission` responde página 404 fora da API | o mesmo middleware serve os dois adapters; o ramo JSON não mudou |
| `LastActiveAdministratorException::render()` por adapter | uma exceção de domínio: 409 JSON na API, `back()->with('error')` na web |
| CSS em `public/css/admin.css` | o CSS inline da 4.1 cresceria demais; um arquivo estático evita build (o Vite/Tailwind do projeto não é usado pelo admin) |

## Desativação e reativação

```text
DeactivateUser (já inativo → nada, sem auditoria)
  DB::transaction
    ├ EnsureActiveAdministratorRemains::beforeRemoving   (lock na linha do perfil admin, 409)
    ├ tokens()->delete()                                 (contados para a auditoria)
    ├ is_active = false
    ├ sessões web removidas (driver database)
    └ UserDeactivated → audit user_deactivated {revoked_tokens, ended_sessions}
```

- **Autodesativação: proibida** (Policy, 403, sem botão). Ela encerraria a
  sessão atual e é o caminho mais curto para um lockout; o último admin já é
  protegido pela invariante, mas um admin entre vários também não deve
  conseguir se trancar para fora por engano.
- **Reativação** (`ActivateUser`) só volta a permitir login. Tokens apagados
  não voltam, nenhum token é emitido e sessões removidas não voltam. Um
  cookie antigo foi testado por HTTP real e é recusado.
- Com driver de sessão diferente de `database`, a sessão de quem foi
  desativado termina no próximo request (`EnsureUserIsActive`). Se a conta
  for reativada antes disso, aquela sessão volta a valer. É um risco
  residual registrado, inexistente na configuração do projeto
  (`SESSION_DRIVER=database`).

## Funcionalidades entregues

- Listagem paginada (15 por página, `withQueryString`) com nome, e-mail,
  status, perfis (eager loading), data de criação e ações permitidas.
- Busca por nome ou e-mail (`ILIKE`, curingas escapados). **CPF não é
  pesquisável nem exibido** ([FIND-008](../findings/README.md#find-008--hash-de-cpf-é-reversível-por-força-bruta)).
- Detalhe: e-mail, status, perfis, número de tokens de API, criação e
  atualização.
- Criação (nome, e-mail, senha + confirmação; conta ativa e sem perfis).
- Edição (nome e e-mail apenas).
- Ativar/desativar com confirmação.
- Atribuição de perfis por checkboxes.
- Navegação lateral por permissão, flash messages e página 404 nativa.

## Interface

Preto, alto contraste, sem framework JS. O único JavaScript novo é a
confirmação de formulários com `data-confirm`. Para acessibilidade: link
"Ir para o conteúdo", `aria-current` na navegação, labels em todos os campos,
erros ligados por `aria-describedby`, `role="status"`/`role="alert"` nas
mensagens, tabela com `caption`, `th[scope]` e textos de ação para leitor de
tela. Em telas estreitas, a sidebar vira barra horizontal e a tabela rola
dentro da própria moldura.

**Checkpoint visual** (servidor real sobre o banco descartável `napi_test`,
HTML capturado por `curl` e renderizado no Edge headless a 1280 px e num
iframe de 390 px): landing com erro de acesso, shell, listagem, busca,
paginação, formulário com erros, detalhe com flash de sucesso e de conflito,
usuário inativo e edição. Os fluxos de login, CSRF (419), logout, desativação
com segunda sessão, reativação e conflito do último admin foram exercidos por
HTTP. Não foram verificados num navegador interativo: o `confirm()` e o
foco/Esc do painel de login.

## Auditoria

Um único ponto por fato, a Action. Controllers e listeners não auditam
manualmente.

| Ação | Status |
|---|---|
| `user_created`, `user_updated` | reutilizadas, agora disparadas por `CreateUser`/`UpdateUser` |
| `profile_assigned` | reutilizada (`AssignProfilesToUser`) |
| `user_deactivated` (`revoked_tokens`, `ended_sessions`) | nova |
| `user_activated` | nova |
| `login`/`logout` | reutilizadas; o login recusado por falta de acesso não gera `login` |

Ator, IP e User-Agent vêm do `AuditContext`. A senha nunca entra na trilha
(testado).

## Testes

| Arquivo | Testes |
|---|---|
| `tests/Feature/Admin/UserManagementTest.php` (novo) | 44: acesso, listagem, detalhe, criação, edição, desativação, ativação, perfis e entre canais |
| `tests/Feature/Security/RouteCoverageTest.php` (novo) | 8: varredura de rotas da API e do admin ([FIND-021](../findings/README.md#find-021--lacunas-de-testes-de-segurança)) |
| `tests/Feature/Admin/AdminShellTest.php` (ajustado) | 19: os usuários dos testes de login passaram a ter perfil `admin` (factory sem perfil não entra mais), e o stand-in `/admin/_protected` foi trocado pela rota real `/admin/users` |
| `tests/Pest.php` | seed também em `Feature/Admin`; helpers `adminUser()` e `adminLogin()` |

Suíte completa: **204 testes**, incluindo os 80 de `tests/Feature/Security`
da 3.2, sem alteração neles. A verificação por mutação confirmou que os
testes falham ao remover `admin.access` das rotas e ao remover a checagem de
acesso do login.

Um achado de teste: nos testes, o session store em memória é compartilhado
entre requisições, então "cookie web na API" daria um falso 200. A garantia
foi verificada por HTTP real (401) e virou um teste estrutural (nenhuma rota
da API inicia sessão).

## Findings

| Finding | Na Fase 4.2 |
|---|---|
| FIND-021 | **RESOLVED**: `RouteCoverageTest` |
| FIND-022 | **RESOLVED**: [ADR-0009](../architecture/decisions/ADR-0009-web-session-and-bearer-adapters.md), sem SPA por cookie |
| FIND-003 | endpoint de desativação entregue (status já era RESOLVED) |
| FIND-008 | **OPEN (deferred)**: CPF fora da UI, sem busca; abordagem recomendada registrada no finding |
| FIND-020 | fora do escopo (envelopes JSON intocados) |
| Novos | nenhum |

## Fora do escopo

UI de perfis, menus, tokens e auditoria; dashboard; troca de senha pelo
admin; FIND-008, FIND-014, FIND-016, FIND-018,
FIND-019 e o restante do FIND-020.

## Complemento — exclusão de usuários e página inicial

- Exclusão na listagem e no detalhe, com confirmação, CSRF e método DELETE.
- Mesma Policy e Action da API: sem autoexclusão, proteção das contas de
  administrador e invariante do último administrador; auditoria user_deleted.
- Exclusão lógica: preserva os dados históricos e impede autenticação da conta.
- A rota pública / mantém somente um fundo preto, sem o cartão inicial do Laravel.
