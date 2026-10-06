# Estrutura do projeto

[← Visão geral](overview.md)

Apenas diretórios com código próprio do NAPI. O restante é o esqueleto padrão
do Laravel 13.

```text
app/
├── Domain/IAM/
│   ├── Actions/               Casos de uso compartilhados pela API e pelo admin (transacionais)
│   │   ├── AssignProfilesToUser.php
│   │   ├── CreateUser.php, UpdateUser.php                (Fase 4.2)
│   │   ├── ActivateUser.php, DeactivateUser.php          (Fase 4.2)
│   │   ├── DeleteUser.php
│   │   └── SyncMenuPermissions.php
│   ├── Exceptions/LastActiveAdministratorException.php   409 na API, flash no admin
│   ├── AuditContext.php       Ator, IP e User-Agent carregados pelos eventos
│   └── EnsureActiveAdministratorRemains.php              Invariante INV-08
├── DTOs/                      Dados validados HTTP → Action
│   ├── AssignProfilesToUserDto.php
│   ├── CreateUserDto.php, UpdateUserDto.php              (Fase 4.2)
│   └── SyncMenuPermissionsDto.php
├── Events/                    Fatos de domínio (síncronos)
│   ├── Contracts/Auditable.php
│   ├── PermissionChanged.php, ProfileAssigned.php
│   ├── UserLoggedIn.php, UserLoggedOut.php
│   ├── UserCreated.php, UserUpdated.php, UserDeleted.php
│   ├── UserActivated.php, UserDeactivated.php            (Fase 4.2)
│   └── TokenCreated.php, TokenRevoked.php
├── Listeners/                 Reações; hoje, apenas auditoria
│   └── RecordAuditLog.php     Uma linha por evento Auditable
├── Http/
│   ├── Admin/AdminNavigation.php  Seções do admin e regra de entrada (Fase 4.2)
│   ├── Controllers/Api/V1/    Controllers REST versionados
│   │   ├── Auth/{AuthController, TokenController}.php
│   │   ├── MenuController.php
│   │   ├── ProfileController.php
│   │   ├── UserController.php
│   │   ├── UserProfileController.php
│   │   └── UserStatusController.php   activate/deactivate (Fase 4.2)
│   ├── Controllers/Web/Admin/ Área administrativa Blade (Fases 4.1 e 4.2)
│   │   ├── Auth/LoginController.php
│   │   ├── HomeController.php
│   │   └── User{,Profile,Status}Controller.php
│   ├── Middleware/
│   │   ├── CheckPermission.php            Permissão funcional por rota (404 JSON ou página)
│   │   ├── EnsureTokenAbility.php         Ability do token pelo método HTTP (403)
│   │   ├── EnsureUserIsActive.php         Encerra sessão web de inativo (grupo web)
│   │   └── EnsureUserCanAccessAdmin.php   `admin.access`: entrada na área administrativa
│   ├── Requests/              FormRequests (namespace plano, Auth/ separado: Login, TokenStore)
│   │   └── Web/Admin/         Login web e requests de usuários, que estendem os da API
│   └── Resources/             UserResource, ProfileResource, MenuResource
├── Models/                    User, UserDetails, Profile, Menu, AuditLog
│                              + pivots UserProfile, MenuProfile
├── Policies/                  UserPolicy, ProfilePolicy, MenuPolicy
└── Providers/AppServiceProvider.php
bootstrap/app.php              Rotas, alias de middleware, exceções JSON
config/sanctum.php             Expiração (7 dias) e prefixo de token (napi_)
database/
├── migrations/                citext, users, tokens, IAM, audit_logs
├── factories/                 User, UserDetails, Profile, Menu
└── seeders/                   Perfis, menus de sistema, usuários de demonstração
routes/api.php                 Todas as rotas /api/v1
routes/web.php                 Área administrativa /admin
resources/views/               layouts/admin(-shell), admin/** (Blade)
public/css/admin.css           CSS da área administrativa, sem build
tests/
├── Feature/IAM/               Testes HTTP (seed automático via tests/Pest.php)
├── Feature/Security/          Regressão de segurança com tokens Bearer reais (Fase 3.2) e cobertura de rotas
├── Feature/Admin/             Área administrativa por sessão web (Fases 4.1 e 4.2)
└── Feature/{User,Profile,Menu}Test.php   Testes de model/constraints
```

## Convenções observadas

- **Namespace de FormRequests plano** (`App\Http\Requests\ProfileStoreRequest`),
  com subnamespace só para autenticação (`Requests\Auth`). A Fase 3 manteve a
  convenção da Fase 2 em vez de criar subpastas por model, para não haver
  duas classes `UserStoreRequest` em namespaces diferentes.
- **Actions sob `app/Domain/IAM`**, DTOs em `app/DTOs`. Actions expõem um
  método estático `execute()`. Não são resolvidas pelo container, portanto
  não recebem dependências injetadas.
- **Models com atributos PHP 8** (`#[Fillable]`, `#[Hidden]`) em vez de
  propriedades `$fillable`/`$hidden`.
- **Pivots com model próprio** quando há colunas extras tipadas:
  `UserProfile` (`assigned_by`, `assigned_at`) e `MenuProfile` (`can_*`). O
  `MenuProfile` foi criado na Fase 3 para o Larastan tipar `$menu->pivot->can_view`.
- **Rotas explícitas por verbo** em vez de `apiResource`, para que cada verbo
  receba seu próprio `permission:{route_name},{ação}`.
- **Controller base** (`app/Http/Controllers/Controller.php`) usa
  `AuthorizesRequests`, habilitando `$this->authorize()`.

## Ferramentas de qualidade

| Ferramenta | Configuração | Script |
|---|---|---|
| Pest 4 | `tests/Pest.php`, `phpunit.xml` (PostgreSQL `napi_test`) | `composer test` |
| Pint | `pint.json` (`laravel`) | `composer lint` |
| Larastan | `phpstan.neon` (nível 6, `app/`) | `composer analyse` |
