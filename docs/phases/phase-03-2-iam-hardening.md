# Fase 3.2 — IAM Security Hardening & Authorization Consolidation

[← Fases](README.md) · [Findings](../findings/README.md) · [ADR-0008](../architecture/decisions/ADR-0008-layered-authorization-model.md)

**Commits:** `2392411` (permissão funcional e `menus.key`), `85ed964` (abilities, usuário inativo, Policies), `6ba00f0` (invariante e auditoria), `e6e11c8` (suíte de segurança), `dc5066a` (documentação)

## Objetivo

Corrigir os findings prioritários do Reality Check da Fase 3.1 e consolidar a
autorização numa única fonte funcional antes da Fase 4 (área
administrativa). Nenhuma feature de produto nova.

## Método

Para cada finding: reprodução → teste permanente falhando → causa raiz →
correção mínima → teste verde → regressão completa → documentação. A suíte
`tests/Feature/Security/` foi escrita antes das correções. Contra o código
da Fase 3.1, 66 dos 80 testes falharam ou deram erro.

## Decisões tomadas com o responsável pelo projeto

| Pergunta | Decisão |
|---|---|
| O que o perfil `admin` significa na camada funcional? | Superusuário implícito: detém todas as permissões funcionais; regras contextuais e invariantes continuam valendo |
| Qual o escopo do `dev` (FIND-009)? | Só autoatendimento (`/auth/me`); a linha `users.view` sai do seed |
| Como separar a chave de permissão do `route_name` (FIND-005)? | Coluna nova `menus.key`, imutável, e `menus.is_system` |

## Entregue

| Área | Arquivos |
|---|---|
| Autenticação (inativo) | `app/Providers/AppServiceProvider.php` (`authenticateAccessTokensUsing`), hook `updated` em `app/Models/User.php` |
| Abilities | `app/Http/Middleware/EnsureTokenAbility.php`, `app/Http/Requests/Auth/TokenStoreRequest.php`, alias em `bootstrap/app.php` |
| Permissão funcional | `User::hasPermission/permissionKeys/holdsPermissionsOf/isAdministrator`, `Profile::permissionKeys/isAdministrator`, `Menu::permissionFor`, `MenuProfile::ACTIONS/grants`, `CheckPermission` |
| Policies | `UserPolicy`, `ProfilePolicy`, `MenuPolicy` reescritas sobre `hasPermission()` + regras contextuais e anti-escalação |
| Invariante | `app/Domain/IAM/EnsureActiveAdministratorRemains.php`, `Exceptions/LastActiveAdministratorException.php`, `Actions/DeleteUser.php` |
| Auditoria | `app/Domain/IAM/AuditContext.php`, `app/Events/Contracts/Auditable.php`, `app/Listeners/RecordAuditLog.php`; eventos novos `UserLoggedOut`, `UserCreated`, `UserUpdated`, `UserDeleted`, `TokenCreated`, `TokenRevoked`; os três listeners antigos foram removidos |
| Schema | `menus.key` e `menus.is_system` na migration de criação `2026_09_20_005741_create_menus_table.php` (sem produção, não há dados a migrar) |
| Seed | `MenuSeeder` (chaves, `is_system`, sem linhas de matriz) |
| Testes | `tests/Feature/Security/*` (80 testes), helpers em `tests/Pest.php`; quatro testes antigos ajustados ao comportamento decidido |

Checkpoint da fase: 133/133 testes, Pint e Larastan (nível 6) sem erros.

## Testes antigos alterados

| Teste | Antes | Depois | Motivo |
|---|---|---|---|
| `UserProfileSyncTest › blocks removing last admin` | 500 | 409 | FIND-006 |
| `AuthorizationTest › allows user to view themselves` | `GET /users/{self}` 200 | `/auth/me` 200 e `/users/{self}` 404 | decisão do escopo do `dev` (FIND-009) |
| `MenuTest › creates menu` | sem `key` | com `key` | FIND-005 |
| `TokenManagementTest › creates a personal access token` | 30 dias | 7 dias | FIND-015 |

## Fora do escopo

FIND-008, FIND-014, FIND-016, FIND-018, FIND-019 e o restante do FIND-020;
endpoint de desativação de usuário (Fase 4); auditoria de login falho e de
CRUD de perfis/menus (Fase 5). Veja [findings](../findings/README.md).
