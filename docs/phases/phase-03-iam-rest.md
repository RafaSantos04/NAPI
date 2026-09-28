# Fase 3 — IAM REST

[← Fases](README.md) · [IAM](../modules/iam.md) · [API v1](../api/v1.md)

**Commit:** `0ef6ea8` — *feat(iam): dtos, actions, events/listeners, profile & menu endpoints, permission-gated routes*

## Objetivo

Expor o IAM completo via API REST: CRUD de perfis e menus, atribuição de
perfis a usuários e sincronização da matriz de permissões, com a separação
FormRequest → DTO → Action → Event → Listener.

## Entregue

| Item | Arquivos |
|---|---|
| DTOs (2) | `app/DTOs/AssignProfilesToUserDto.php`, `SyncMenuPermissionsDto.php` |
| Actions (2) | `app/Domain/IAM/Actions/AssignProfilesToUser.php`, `SyncMenuPermissions.php` |
| Eventos (3) | `app/Events/ProfileAssigned.php`, `PermissionChanged.php`, `UserLoggedIn.php` |
| Listeners (3) | `app/Listeners/AuditProfileAssignment.php`, `AuditPermissionChange.php`, `AuditUserLogin.php` |
| FormRequests (6) | `AssignProfileRequest`, `ProfileStoreRequest`, `ProfileUpdateRequest`, `SyncMenusRequest`, `MenuStoreRequest`, `MenuUpdateRequest` |
| Controllers (3) | `ProfileController`, `MenuController`, `UserProfileController` |
| Resources (2) | `ProfileResource`, `MenuResource` |
| Policies | `UserPolicy::assignProfiles`, `ProfilePolicy::syncMenus` |
| Pivot model | `app/Models/MenuProfile.php` |
| Rotas | 13 rotas novas em `routes/api.php` |
| Testes (16) | `tests/Feature/IAM/{ProfileTest,MenuTest,UserProfileSyncTest,AuditEventTest}.php` |
| Refatoração | `AuthController::login` passou a disparar `UserLoggedIn` em vez de gravar a auditoria inline |

Checkpoint da fase: 53/53 testes, Pint e Larastan sem erros.

## Decisões tomadas durante a fase

A especificação original da fase trazia contradições internas, que foram
resolvidas assim:

1. **Middleware de matriz nas rotas novas.** A especificação omitia
   `permission:{route_name},{ação}`. Ele foi adicionado para seguir o padrão
   das rotas de usuários. É ele que produz o 404 esperado pelos testes de
   negação.
2. **`assignProfiles` sem bloqueio de auto-atribuição.** Com o bloqueio, a
   regra do último admin (na Action) ficava inalcançável. Com concordância
   do responsável, a Policy passou a exigir apenas o perfil admin, e a Action
   ficou responsável pela invariante
   ([ADR-0004](../architecture/decisions/ADR-0004-policy-based-authorization.md)).
3. **`AuditEventTest` sem `Event::fake()`.** Fingir os eventos impede os
   listeners de rodar, e o teste verificava justamente o efeito deles.
4. **`profile_ids` com `present` em vez de `required|min:1`.** Com
   `required`, o array vazio era rejeitado com 422 antes de chegar à regra do
   último admin.
5. **IP carregado no evento.** Os listeners não leem `request()`. A Action
   captura o IP e o passa ao evento
   ([ADR-0005](../architecture/decisions/ADR-0005-event-driven-audit.md)).
6. **FormRequests em namespace plano**, seguindo a Fase 2, para não
   duplicar `UserStoreRequest`/`UserUpdateRequest` em subpastas.
7. **Pivot `MenuProfile`**, criado para o Larastan tipar
   `$menu->pivot->can_*`.

## Problemas conhecidos originados na fase

Encontrados na Fase 3.1 e ainda não corrigidos:

- **Listeners registrados em duplicidade** ([FIND-002](../findings/README.md#find-002--listeners-de-auditoria-registrados-em-duplicidade)).
  O registro manual no `AppServiceProvider` somou-se à auto-discovery do
  Laravel 13. Os testes não detectaram porque verificam `exists()`.
- A regra do último admin usa `\Exception` e responde 500
  ([FIND-006](../findings/README.md#find-006--regra-do-último-administrador-tem-janela-de-corrida-e-resposta-500)).
- A validação do sync não confere as chaves de menu
  ([FIND-011](../findings/README.md#find-011--validação-incompleta-na-sincronização-de-permissões)).
- A árvore de menus não filtra filhos
  ([FIND-010](../findings/README.md#find-010--árvore-de-menus-não-filtra-filhos-nem-inativos)).
