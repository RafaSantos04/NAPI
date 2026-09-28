# Estrutura do projeto

[← Visão geral](overview.md)

Apenas diretórios com código próprio do NAPI. O restante é o esqueleto padrão
do Laravel 13.

```text
app/
├── Domain/IAM/Actions/        Casos de uso com regra de negócio
│   ├── AssignProfilesToUser.php
│   └── SyncMenuPermissions.php
├── DTOs/                      Dados validados HTTP → Action
│   ├── AssignProfilesToUserDto.php
│   └── SyncMenuPermissionsDto.php
├── Events/                    Fatos de domínio (síncronos)
│   ├── PermissionChanged.php
│   ├── ProfileAssigned.php
│   └── UserLoggedIn.php
├── Listeners/                 Reações; hoje, apenas auditoria
│   ├── AuditPermissionChange.php
│   ├── AuditProfileAssignment.php
│   └── AuditUserLogin.php
├── Http/
│   ├── Controllers/Api/V1/    Controllers REST versionados
│   │   ├── Auth/{AuthController, TokenController}.php
│   │   ├── MenuController.php
│   │   ├── ProfileController.php
│   │   ├── UserController.php
│   │   └── UserProfileController.php
│   ├── Middleware/CheckPermission.php   Matriz de permissões por rota
│   ├── Requests/              FormRequests (namespace plano, Auth/ separado)
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
└── seeders/                   Perfis, menus + matriz, usuários de demonstração
routes/api.php                 Todas as rotas /api/v1
tests/
├── Feature/IAM/               Testes HTTP (seed automático via tests/Pest.php)
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
