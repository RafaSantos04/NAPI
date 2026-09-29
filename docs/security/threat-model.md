# Threat model

[← Segurança](README.md) · [Findings](../findings/README.md) · [Security Lab](../modules/security.md)

Escopo: **somente o que existe hoje**, a API REST v1 do IAM. Criado na Fase
3.1 e atualizado na Fase 3.2 com as mitigações implementadas (seção
[Mitigações da Fase 3.2](#mitigações-da-fase-32)).

**Status:** ✅ mitigado (há evidência no código e teste) · ⚠️ parcial · ❌ não mitigado.
"Confirmado em execução" indica que o comportamento foi reproduzido com um
teste; na Fase 3.2 esses testes passaram a ser permanentes em
`tests/Feature/Security/`.

**Fases relacionadas:** "Fase 4" é a área administrativa Blade; "Fase 5" é
a área de segurança/testes; "Hardening" indica correção sem fase definida.
Veja [phases](../phases/README.md).

## Ativos e atores

- **Ativos**: tokens, matriz de permissões, atribuições de perfil, dados de
  usuários, trilha de auditoria.
- **Atores**: anônimo; usuário autenticado sem perfil; `viewer`; `dev`;
  perfil customizado com permissões delegadas; `admin`; portador de um token
  vazado.

## Mitigações da Fase 3.2

### Privilege escalation through a read-only token

- **Antes**: vulnerabilidade confirmada. Um token só com `read` de um admin executou `DELETE /users/{id}` ([FIND-001](../findings/README.md#find-001--abilities-de-token-não-são-aplicadas)).
- **Mitigação**: `EnsureTokenAbility` exige `read`/`write`/`delete` pelo método HTTP em todas as rotas autenticadas; `POST /tokens` só emite subconjuntos das abilities do token atual.
- **Teste de regressão**: `tests/Feature/Security/TokenAbilityTest.php`.
- **Risco residual**: o login emite as três abilities, então o token de login vazado tem o poder do dono até expirar (7 dias) ou ser revogado. Tokens de sessão (`TransientToken`) passam em qualquer ability, sem impacto enquanto `statefulApi()` estiver desligado.

### Deactivated account keeps API access

- **Antes**: confirmado. Token emitido antes de `is_active = false` continuava com 200 ([FIND-003](../findings/README.md#find-003--usuário-desativado-continua-autenticado)).
- **Mitigação**: `Sanctum::authenticateAccessTokensUsing` recusa o token de dono inativo (401); hook de higiene apaga os tokens ao desativar.
- **Teste de regressão**: `tests/Feature/Security/DisabledUserAuthenticationTest.php`.
- **Risco residual**: não há endpoint de desativação, e ele ficará para a Fase 4.

### Custom profiles ignored or divergent authorization

- **Antes**: confirmado. Um perfil customizado com CRUD completo na matriz recebia 404, porque as Policies só conheciam `admin`/`dev` ([FIND-004](../findings/README.md#find-004--duas-fontes-de-autorização-que-não-se-conhecem)); `dev` listava todos os usuários, mas não podia ver nenhum ([FIND-009](../findings/README.md#find-009--perfil-dev-lista-todos-os-usuários-mas-não-pode-ver-nenhum)).
- **Mitigação**: `User::hasPermission()` é a única fonte funcional, usada por middleware, Policies e árvore ([ADR-0008](../architecture/decisions/ADR-0008-layered-authorization-model.md)). `viewAny` e `view` usam a mesma permissão.
- **Teste de regressão**: `tests/Feature/Security/AuthorizationMatrixTest.php`.
- **Risco residual**: o perfil `admin` continua sendo um conceito de código (slug), com todas as permissões implícitas; isso é intencional e documentado.

### Delegated administrator escalates privileges

- **Antes**: não explorável, porque só admin fazia qualquer coisa. Passaria a ser explorável com a delegação da matriz.
- **Mitigação**: quem não é admin não altera os próprios perfis nem a matriz de um perfil que possui, não concede `admin`, não mexe em contas de administrador e só concede perfis e flags que já tem (`User::holdsPermissionsOf`).
- **Teste de regressão**: `tests/Feature/Security/PrivilegeEscalationTest.php`.
- **Risco residual**: um delegado com `permissions.update` pode **revogar** flags de outros perfis customizados, inclusive flags que ele não tem. É sabotagem, não escalação, fica registrada na auditoria e não afeta perfis de sistema.

### Administrative lockout

- **Antes**: confirmado. Havia janela de corrida, admins inativos contavam como restantes e a resposta era 500 ([FIND-006](../findings/README.md#find-006--regra-do-último-administrador-tem-janela-de-corrida-e-resposta-500)); renomear `menus.index` trancava o admin ([FIND-005](../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema)).
- **Mitigação**: invariante "≥ 1 administrador ativo" em transação com `SELECT … FOR UPDATE` no perfil admin, aplicada à remoção de perfil e à exclusão de usuário, respondendo 409. Chave de permissão `menus.key` imutável e separada do `route_name`; menus de sistema não excluíveis; admin com todas as permissões implícitas.
- **Teste de regressão**: `tests/Feature/Security/AdminInvariantTest.php`, `AuthorizationMatrixTest › Stable permission keys`.
- **Risco residual**: a concorrência não tem teste automatizado (limitação do `RefreshDatabase`); o lock foi verificado manualmente contra o PostgreSQL. A desativação de usuário não passa pela invariante, porque ainda não há endpoint.

### Audit trail not trustworthy

- **Antes**: confirmado. Duas linhas por evento ([FIND-002](../findings/README.md#find-002--listeners-de-auditoria-registrados-em-duplicidade)); CRUD de usuário e tokens sem trilha ([FIND-012](../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria)); ator lido de `auth()` e sem atomicidade ([FIND-013](../findings/README.md#find-013--contexto-de-auditoria-acoplado-ao-processo-http-e-sem-atomicidade)).
- **Mitigação**: um único listener por discovery; eventos com `AuditContext`; evento dentro da transação da operação; auditoria de usuário e tokens.
- **Teste de regressão**: `tests/Feature/Security/AuditIntegrityTest.php`.
- **Risco residual**: login falho e CRUD de perfis/menus continuam sem trilha (adiados, veja [audit.md](audit.md#cobertura)); logs mutáveis ([FIND-014](../findings/README.md#find-014--audit_logs-é-mutável)).

## Autenticação

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Brute force em uma conta | ⚠️ | `throttle:login`, 6 tentativas / 30 min por **email + IP** | ✅ `rate limits login…` | Tentativas distribuídas por IPs não são limitadas; não há bloqueio de conta nem alerta | Hardening |
| Credential stuffing (muitos e-mails, um IP) | ❌ | A chave inclui o e-mail, então cada e-mail novo tem cota própria | ❌ | Alto volume por IP é possível ([FIND-016](../findings/README.md#find-016--rate-limiting-restrito-ao-login-e-por-emailip)) | Hardening |
| Vazamento de token | ⚠️ | Hash SHA-256 no banco; prefixo `napi_`; expiração de 7 dias; revogação; **abilities aplicadas** | ✅ `TokenAbilityTest` | Um token de login vazado tem o poder do dono até expirar | — |
| Replay de token | ⚠️ | Expiração global de 7 dias; logout revoga todos | ✅ logout | Válido por até 7 dias sem rotação nem vínculo com IP ou dispositivo | Hardening |
| Usuário desativado com token válido | ✅ | Callback de autenticação do Sanctum + limpeza ao desativar | ✅ `DisabledUserAuthenticationTest` | — | — |
| Usuário excluído (soft delete) com token | ✅ | O Sanctum não resolve `tokenable` excluído | ✅ `rejects the token of a soft-deleted user` | Linhas de token órfãs permanecem | — |
| Enumeração de usuários pelo login | ⚠️ | Mensagem única `Invalid credentials.` | ✅ senha inválida | Diferença de tempo (`exists:users` evita o bcrypt); `Account is inactive.` confirma credenciais válidas ([FIND-018](../findings/README.md#find-018--sinais-de-enumeração-no-login)) | Fase 5 |

## Autorização

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Auto-escalação (atribuir perfis a si) | ✅ | `users.update` + `assignProfiles` (não-admin não altera a si) | ✅ | — | — |
| Escalação por delegação (conceder mais do que tem) | ✅ | `holdsPermissionsOf` em `assignProfiles` e `syncMenus` | ✅ | Revogação por delegado (veja acima) | — |
| Escalação via matriz (dar permissão ao próprio perfil) | ✅ | `permissions.update` + `syncMenus` bloqueia o perfil do próprio ator | ✅ | — | — |
| IDOR em `GET /users/{id}` | ✅ | `UserPolicy::view` + 404 | ✅ | — | — |
| Exposição via listagem | ✅ | `viewAny` e `view` exigem `users.view`; `dev` só autoatendimento | ✅ `limits dev to self-service` | — | — |
| IDOR em tokens | ✅ | Consulta escopada no dono; 404 para token alheio | ✅ | — | — |
| Mass assignment | ✅ | `validated()`, `#[Fillable]`, `shouldBeStrict()` fora de produção | ✅ `is_system` via HTTP | Em produção, atributos não fillable são descartados em silêncio | — |
| Bypass por rota nova sem controle | ⚠️ | `token.ability` cobre todo o grupo autenticado por padrão; permissão funcional ainda é declarada por rota | ❌ | Uma rota nova sem `permission:` e sem Policy fica acessível a qualquer autenticado; não há teste que varra as rotas | Fase 5 |
| Confiança indevida na interface (menu oculto) | ✅ | A árvore é projeção da camada funcional e filtra filhos e inativos | ✅ `MenuTreeTest` | — | — |
| Adulteração de perfis de sistema | ✅ | `is_system` em `update`/`delete`/`syncMenus`; campo não aceito na entrada | ✅ | — | — |
| Lockout administrativo via perfis | ✅ | INV-07, INV-08 (lock + ativos), INV-04 | ✅ | Concorrência verificada manualmente | — |
| Lockout administrativo via menus | ✅ | `key` imutável, menus de sistema não excluíveis, admin com permissões implícitas | ✅ | — | — |
| Escopo excessivo de token | ✅ | `EnsureTokenAbility` + emissão só de subconjuntos | ✅ | — | — |

## Entrada

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Campos inesperados | ✅ | Só `validated()` é persistido; `key` `prohibited` na atualização de menu | ✅ parcial | — | — |
| SQL injection | ✅ | Eloquent com bindings; a coluna `can_{ação}` é lida pelo model a partir de uma lista fechada (`MenuProfile::ACTIONS`) | ❌ | — | — |
| Referências inválidas | ⚠️ | `exists:` em `profile_ids.*` e `parent_id`; chaves de `permissions` validadas contra `menus` | ✅ `rejects unknown menu ids…` | Menu pode ser pai de si mesmo ([FIND-019](../findings/README.md#find-019--menu-pode-ser-pai-de-si-mesmo)); a árvore ignora ciclos | Fase 4 |
| Dados excessivos | ⚠️ | `max:` em strings; paginação padrão (15); `permissions` até 200 itens | ❌ | `profile_ids` sem limite de tamanho | Hardening |

## API

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Ausência de rate limit | ❌ | Só o login é limitado | ❌ | Endpoints autenticados sem limite ([FIND-016](../findings/README.md#find-016--rate-limiting-restrito-ao-login-e-por-emailip)) | Hardening |
| Enumeração de recursos | ✅ | ULIDs; 404 uniforme para "sem permissão funcional" e "inexistente" | ✅ | Tokens usam `bigint`, mas com escopo no dono | — |
| Exposição excessiva | ✅ | Resources com lista explícita de campos; `password` oculto; `user_details` não exposto; listagem e árvore filtradas | ✅ | — | — |
| Respostas inconsistentes | ⚠️ | 404 funcional, 403 contextual/ability, 409 invariante | ✅ | Controllers ainda negam `index`/`show` à mão; envelope de resposta divergente ([FIND-020](../findings/README.md#find-020--respostas-de-autorização-e-de-tokens-inconsistentes)) | Hardening |
| Vazamento de detalhes em erro | ✅ | Regras de negócio conhecidas viram 409/422; `LastActiveAdministratorException` fora do report | ✅ | — | — |

## Auditoria

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Ações sem trilha | ⚠️ | Login, logout, usuário (criar/alterar/excluir), atribuição de perfis, matriz, tokens | ✅ | Login falho e CRUD de perfis/menus adiados ([audit.md](audit.md#cobertura)) | Fase 5 |
| Metadados insuficientes | ⚠️ | Ator, IP e UA em todos os eventos; `subject_type` em FQCN | ✅ | Sem estado anterior em `meta` | Fase 5 |
| Registros duplicados | ✅ | Um único registro de listener (discovery) | ✅ `count() === 1` | — | — |
| Trilha sem a operação, ou operação sem trilha | ✅ | Evento síncrono dentro da transação | ✅ `leaves no audit trail for a rejected change` | — | — |
| Informação sensível no log | ✅ | Sem senhas ou tokens em `meta`; `user_updated` só com nomes de campos | ✅ | IP/UA sem política de retenção (LGPD) | Hardening |
| Alteração dos próprios logs | ❌ | Nenhum endpoint de escrita | ❌ | Mutável no model e no banco ([FIND-014](../findings/README.md#find-014--audit_logs-é-mutável)) | Fase 5 |

## Dados pessoais

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Reversão de CPF | ❌ | CPF guardado como SHA-256 | ❌ | SHA-256 sem salt em um espaço de ~10⁹ valores é reversível por força bruta ([FIND-008](../findings/README.md#find-008--hash-de-cpf-é-reversível-por-força-bruta)) | Hardening |
