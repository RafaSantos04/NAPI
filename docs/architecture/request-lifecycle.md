# Ciclo de vida da requisição

[← Visão geral](overview.md) · [Autorização](../security/authorization.md) · [API v1](../api/v1.md)

Este documento segue uma requisição real pelo sistema. A ordem dos
middlewares, já ordenada pela prioridade do Laravel, foi confirmada com
`Router::resolveMiddleware(gatherRouteMiddleware())` para `users.show` (Fase 3.2):

```text
Authenticate:sanctum → SubstituteBindings → EnsureTokenAbility → CheckPermission:users.view
```

O grupo `api` do Laravel 13 contém apenas `SubstituteBindings`. Não há
throttle global nem `EnsureFrontendRequestsAreStateful` (o
`bootstrap/app.php` não chama `statefulApi()`).

## Fluxo completo: `PUT /api/v1/users/{user}/profiles`

É o fluxo mais longo do sistema e passa por todas as camadas do
[ADR-0008](decisions/ADR-0008-layered-authorization-model.md).

```mermaid
sequenceDiagram
    autonumber
    participant C as Cliente
    participant S as auth:sanctum
    participant T as EnsureTokenAbility
    participant P as CheckPermission
    participant R as AssignProfileRequest
    participant Pol as UserPolicy
    participant Ct as UserProfileController
    participant A as AssignProfilesToUser
    participant DB as PostgreSQL
    participant L as RecordAuditLog

    C->>S: Bearer token
    S-->>C: 401 se ausente, inválido, expirado ou dono inativo
    S->>T: usuário autenticado; {user} resolvido (404 se não existe)
    T-->>C: 403 se o token não tem 'write'
    T->>P: ability ok
    P->>P: hasPermission('users.update')
    P-->>C: 404 se não houver permissão funcional
    P->>R: authorize()
    R->>Pol: can('assignProfiles', [$user, profile_ids])
    Pol-->>C: 403 se escalaria privilégio
    R-->>C: 422 se profile_ids inválido
    R->>Ct: dados validados
    Ct->>A: DTO + AuditContext
    A->>DB: BEGIN
    A->>DB: SELECT perfil admin FOR UPDATE; há outro admin ativo?
    A-->>C: 409 + ROLLBACK se removeria o último admin ativo
    A->>DB: sync(user_profiles), assigned_by nos novos
    A->>L: event(ProfileAssigned) síncrono
    L->>DB: INSERT audit_logs
    A->>DB: COMMIT
    Ct-->>C: 200 + UserResource com profiles
```

## Etapas e o que cada uma garante

| # | Etapa | Arquivo | Garante | Resposta em falha |
|---|---|---|---|---|
| 1 | Autenticação | `Authenticate:sanctum` + callback em `AppServiceProvider` | Token existe, não expirou, dono existe, não está soft-deleted e está **ativo** | 401 |
| 2 | Route model binding | `SubstituteBindings` | O ULID existe; `User` aplica o escopo de soft delete | 404 |
| 3 | Ability do token | `app/Http/Middleware/EnsureTokenAbility.php` | O token tem `read`/`write`/`delete` conforme o método | 403 |
| 4 | Permissão funcional | `app/Http/Middleware/CheckPermission.php` → `User::hasPermission()` | Algum perfil do ator tem `can_{ação}` no menu de `key` informado (admin: sempre) | 404 |
| 5 | Regra contextual | `FormRequest::authorize()` → Policy | Regras de instância e anti-escalação | 403 |
| 6 | Validação | `FormRequest::rules()` | Formato, unicidade e referências; só campos declarados chegam a `validated()` | 422 |
| 7 | Caso de uso | Controller → DTO → Action, em `DB::transaction` | Invariantes de domínio (último admin ativo) | 409 |
| 8 | Persistência | Eloquent → PostgreSQL | Constraints de FK/unique no banco | 500 (`QueryException`) |
| 9 | Evento e auditoria | Event `Auditable` → `RecordAuditLog`, na mesma transação | Exatamente uma linha em `audit_logs`, ou nenhuma se a operação falhar | rollback da operação |
| 10 | Resposta | API Resource | Contrato JSON; `password` oculto por `#[Hidden]` | — |

Pela prioridade de middleware do Laravel, o binding (etapa 2) roda logo após a
autenticação, então um ULID inexistente responde 404 antes da checagem de
ability.

### Onde a Policy é chamada em cada tipo de endpoint

| Ação | Onde a Policy é chamada | Status em negação |
|---|---|---|
| `index`, `show` (users, profiles, menus) | Manualmente no controller com `$request->user()->can()` | 404 (JSON `{"message":"Not found."}`) |
| `store`, `update`, `syncMenus`, atribuição de perfis | `FormRequest::authorize()` | 403 |
| `destroy` | `$this->authorize()` no controller | 403 |
| `menus.tree` | Nenhuma; a árvore só projeta `hasPermission()` do próprio ator | — |
| `tokens.*`, `auth.me`, `auth.logout` | Nenhuma Policy nem permissão funcional; escopo pelo próprio usuário | — |

Como a permissão funcional (etapa 4) roda antes e já responde 404 a quem não
a tem, os 403 da etapa 5 só são vistos por quem tem acesso à área mas
esbarrou numa regra contextual. É o caso de um admin tentando excluir um
perfil de sistema, ou de um delegado tentando conceder um perfil acima do
próprio.

## Fluxos mais curtos

**CRUD de usuário** (`POST`/`PUT /users`): etapas 1 a 6; o controller grava e
dispara `UserCreated`/`UserUpdated` dentro de `DB::transaction`.

**CRUD de perfil e menu**: etapas 1 a 6, e depois
`Model::create($request->validated())` ou `$model->update(...)`, sem evento
(auditoria adiada, veja [audit.md](../security/audit.md#cobertura)).

**Login** (`POST /auth/login`): sem `auth:sanctum`; passa pelo rate limiter
nomeado `login`, pelo `LoginRequest` e pelo `AuthController`, que dispara
`UserLoggedIn` e emite um token com `read`, `write` e `delete` na mesma
transação. Veja [authentication.md](../security/authentication.md).

## Ordem de rotas

`GET /menus/tree` é registrada **antes** de `GET /menus/{menu}` em
`routes/api.php`. Se a ordem fosse invertida, `tree` seria capturado como
`{menu}` e a rota responderia 404. `MenuTreeTest` falharia se ela quebrasse.

## Tratamento de exceções

`bootstrap/app.php` força respostas JSON para `api/*` e para requisições que
esperam JSON. `AuthorizationException` (inclusive `MissingAbilityException`
do Sanctum) vira 403, `ModelNotFoundException` vira 404 e
`ValidationException` vira 422. `LastActiveAdministratorException` se
renderiza como 409 e fica fora do report (`dontReport`), porque é uma regra
de negócio e não um erro do servidor. Qualquer outra exceção vira 500.
