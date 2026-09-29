# Testes de segurança e invariantes

[← Testes](README.md) · [Invariantes](../security/security-policies.md) · [Threat model](../security/threat-model.md)

Os testes abaixo existem para **impedir regressões de segurança**, não para
aumentar cobertura. Cada linha liga uma invariante ao teste que falharia se
ela quebrasse.

## Suíte de regressão da Fase 3.2: `tests/Feature/Security/`

Todos usam **tokens Sanctum reais** (`Authorization: Bearer`) através dos
helpers de `tests/Pest.php`, então cada requisição passa por
`auth:sanctum` → resolução do token → ability → permissão → Policy →
controller. `actingAs()` não faria isso, porque anexa um `TransientToken`
que passa em qualquer ability.

| Helper (`tests/Pest.php`) | Uso |
|---|---|
| `tokenFor($user, $abilities)` | cria um token real e devolve o texto claro |
| `asToken($user, $method, $uri, $data, $abilities)` | envia a requisição com um token novo, depois de `forgetGuards()` (o app de teste mantém o usuário resolvido entre requisições) |
| `seededUser('admin'\|'dev'\|'viewer')` | usuários do seed |
| `profileWithPermissions([...])`, `userWithPermissions([...])` | perfil customizado com exatamente as flags pedidas, por exemplo `['users' => ['view', 'update']]` |

Cada arquivo começou **falhando** contra o código da Fase 3.1 (80 testes: 14
passavam, 28 falhavam e 38 davam erro por esquema ausente), e só passou com a
correção correspondente.

| Arquivo | Testes | Findings | Garante |
|---|---|---|---|
| `TokenAbilityTest` | 14 | FIND-001, 015, 020 | token inválido → 401; `read` permite GET e bloqueia POST/PUT/DELETE; `delete` exigido para DELETE; `write` não passa pela permissão IAM; token não emite token mais poderoso; token de login faz tudo que o dono pode; teto de 7 dias; token alheio → 404 |
| `DisabledUserAuthenticationTest` | 6 | FIND-003 | token de usuário desativado → 401 (mesmo sem limpeza); soft delete → 401; desativar apaga tokens; outras atualizações não; login inativo sem token |
| `AuthorizationMatrixTest` | 23 | FIND-004, 005, 007, 009 | `hasPermission()` real e fail-closed; admin implícito; `is_active` de menu não afeta autorização; perfil customizado funciona via HTTP; listagem e detalhe coerentes; `dev` só autoatendimento; renomear menu não quebra acesso; `key` imutável; menu de sistema não excluível; `is_system` ignorado na entrada |
| `PrivilegeEscalationTest` | 15 | FIND-004, 011, 021 | atribuir perfis sem `users.update`; atribuir a si; conceder `admin`; conceder perfil acima do próprio; rebaixar/editar/excluir administrador; alterar matriz sem permissão; editar a matriz do próprio perfil; conceder flag que não tem; perfil de sistema imutável; menu inexistente → 422; revogar tudo |
| `AdminInvariantTest` | 7 | FIND-006 | último admin ativo → 409; admin inativo e admin excluído não contam; com dois admins ativos a mudança passa; exclusão via Action respeita a invariante; rejeição não gera auditoria |
| `AuditIntegrityTest` | 8 | FIND-002, 012, 013, 017 | `count() === 1` para login, logout, atribuição, matriz, usuário (criar/alterar/excluir) e token (criar/revogar); ator correto; `assigned_by` no pivot; contexto vem do evento, não da sessão |
| `MenuTreeTest` | 7 | FIND-010 | admin vê todos os ativos; inativos (raiz e filho) ocultos; filho sem `view` oculto; filho de pai oculto oculto; `dev` recebe 200 com árvore vazia; mais de um nível; aparecer na árvore não concede acesso |

## Invariante → teste

| Invariante | Teste | Arquivo |
|---|---|---|
| INV-01 autenticação obrigatória | `returns 401 when accessing protected route without token`, `rejects an invalid bearer token` | `IAM/AuthenticationTest`, `Security/TokenAbilityTest` |
| Rate limit de login | `rate limits login after 6 attempts in 30 minutes` | `IAM/AuthenticationTest` |
| Mensagem uniforme | `rejects invalid password` | `IAM/AuthenticationTest` |
| Logout revoga tokens | `logs out and revokes tokens` | `IAM/AuthenticationTest` |
| INV-02 gerenciar perfis exige permissão | `blocks viewer from creating profile`, `lets a custom profile with the permission use the endpoint`, `denies with 404 when the permission is missing` | `IAM/ProfileTest`, `Security/AuthorizationMatrixTest` |
| INV-03 matriz sem escalação | `Permission matrix escalation` (6) | `Security/PrivilegeEscalationTest` |
| INV-04 perfil de sistema | `blocks deletion of system profile`, `keeps system profiles immutable, including for admin` | `IAM/ProfileTest`, `Security/PrivilegeEscalationTest` |
| INV-05 perfil com usuários | `blocks deletion of profile with users`, `prevents deleting a profile that still has users` | `IAM/ProfileTest`, `IAM/AuthorizationTest` |
| INV-06 menu com filhos ou de sistema | `blocks deletion of menu with children`, `does not allow deleting a system menu` | `IAM/MenuTest`, `Security/AuthorizationMatrixTest` |
| INV-07 auto-exclusão | `blocks admin from deleting themselves` | `IAM/AuthorizationTest` |
| INV-08 admin ativo | `AdminInvariantTest` (7), `blocks removing last admin` (409) | `Security/AdminInvariantTest`, `IAM/UserProfileSyncTest` |
| INV-09 ninguém concede mais do que tem | `Profile assignment escalation` (7), `blocks user from assigning profiles to themselves` | `Security/PrivilegeEscalationTest`, `IAM/UserProfileSyncTest` |
| INV-10 listagem = detalhe | `keeps list and detail consistent…`, `limits dev to self-service`, `prevents user from viewing other users`, `allows user to view themselves through /auth/me` | `Security/AuthorizationMatrixTest`, `IAM/AuthorizationTest` |
| INV-11 entrada não define autorização | `has fillable protection`, `does not accept is_system from the client`, `does not allow changing a menu key` | `UserTest`, `Security/AuthorizationMatrixTest` |
| INV-12 tokens do dono | `returns 404 when revoking a token that is not the caller's` | `Security/TokenAbilityTest` |
| INV-13 uma entrada por evento | `AuditIntegrityTest` (8), `leaves no audit trail for a rejected change` | `Security/*` |
| INV-14 trilha sobrevive | `force deleting a user nullifies its audit logs instead of deleting them` | `UserTest` |
| INV-15 e-mail único | `email is unique`, `email is case-insensitive (citext)` | `UserTest` |
| INV-16 inativo não autentica | `DisabledUserAuthenticationTest` (6) | `Security/DisabledUserAuthenticationTest` |
| INV-17 token não excede abilities | `TokenAbilityTest` | `Security/TokenAbilityTest` |
| INV-18 identidade estável de permissão | `Stable permission keys` | `Security/AuthorizationMatrixTest` |
| Integridade de vínculos | testes de `force deleting … cascades` | `UserTest`, `ProfileTest`, `MenuTest` |

## Lacunas

| Lacuna | Motivo / relacionado |
|---|---|
| Corrida concorrente na invariante de administrador | `RefreshDatabase` usa uma única transação por teste, então duas conexões simultâneas não enxergam os dados. O lock foi verificado manualmente no PostgreSQL (INV-08) |
| Toda rota autenticada declara `permission:` ou é intencionalmente pública (teste de varredura de rotas) | threat model, Fase 5 |
| 401 em cada rota individual de Profiles, Menus e Tokens | INV-01; o grupo é único, e o 401 é testado em `/auth/me` e com token inválido |
| Login falho auditado | adiado (FIND-012) |
| Enumeração no login por tempo | FIND-018, Fase 5 |
| Rate limit das rotas autenticadas | FIND-016 |
