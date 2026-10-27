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
│   │   ├── CreateProfile.php, UpdateProfile.php, DeleteProfile.php   (Fase 4.3)
│   │   ├── CreateMenu.php, UpdateMenu.php, DeleteMenu.php            (Fase 4.4)
│   │   ├── SyncMenuProfiles.php       matriz vista pelo menu (Fase 4.4)
│   │   └── SyncMenuPermissions.php
│   ├── Exceptions/            409 na API, flash no admin
│   │   ├── LastActiveAdministratorException.php
│   │   └── ResourceInUseException.php                    perfil com usuários, menu com submenus (Fase 4.3)
│   ├── AuditContext.php       Ator, IP e User-Agent carregados pelos eventos
│   └── EnsureActiveAdministratorRemains.php              Invariante INV-08
├── Domain/Security/           Security Lab (Fases 5.1 e 5.2, ADR-0010)
│   ├── Actions/               Únicos lugares com cenário vulnerável
│   │   ├── RunIdorTest.php                               (Fase 5.1)
│   │   └── RunMassAssignmentTest.php                     (Fase 5.2)
│   └── Enums/                 SecurityTest, SecurityTestScenario,
│                              SecurityTestExecutionStatus, ...ObservedOutcome, ...Verdict
├── Domain/Tenancy/            Organizations e memberships (P1, ADR-0011)
│   ├── Actions/               {Create,Update}Organization, AddOrganizationMember,
│   │                          UpdateOrganizationMembership, RemoveOrganizationMember
│   ├── Enums/                 OrganizationRole (owner|manager|member, P2), OrganizationPermission
│   │                          (catálogo fechado de 19 keys), OrganizationMembershipStatus
│   ├── OrganizationContext.php                   Tenant da requisição; identifica, não autoriza
│   └── EnsureActiveOrganizationOwnerRemains.php  Invariante do último owner
├── Domain/Projects/           Árvore Project → Application → Environment (P2, ADR-0012)
│   ├── Actions/               {Create,Update,Archive,Restore}{Project,Application,Environment}:
│   │                          ancestrais FOR SHARE de cima para baixo, linha FOR UPDATE
│   ├── Enums/                 EnvironmentClassification
│   └── Exceptions/            {Project,Application,Environment}LifecycleException (409)
├── DTOs/                      Dados validados HTTP → Action
│   ├── AssignProfilesToUserDto.php
│   ├── CreateUserDto.php, UpdateUserDto.php              (Fase 4.2)
│   ├── CreateProfileDto.php, UpdateProfileDto.php        (Fase 4.3)
│   ├── CreateMenuDto.php, UpdateMenuDto.php              (Fase 4.4)
│   ├── SyncMenuProfilesDto.php                           (Fase 4.4)
│   ├── RunIdorTestDto.php                                (Fase 5.1)
│   ├── RunMassAssignmentTestDto.php                      (Fase 5.2)
│   ├── {Create,Update}OrganizationDto, AddOrganizationMemberDto,
│   │   UpdateOrganizationMembershipDto                   (P1)
│   ├── {Create,Update}{Project,Application,Environment}Dto   (P2)
│   └── SyncMenuPermissionsDto.php
├── Events/                    Fatos de domínio (síncronos)
│   ├── Contracts/Auditable.php
│   ├── PermissionChanged.php, ProfileAssigned.php
│   ├── UserLoggedIn.php, UserLoggedOut.php
│   ├── UserCreated.php, UserUpdated.php, UserDeleted.php
│   ├── UserActivated.php, UserDeactivated.php            (Fase 4.2)
│   ├── Profile{Created,Updated,Deleted}.php              (Fase 4.3)
│   ├── Menu{Created,Updated,Deleted}.php                 (Fase 4.4)
│   ├── SecurityTestExecuted.php                          (Fase 5.1)
│   ├── Organization{Created,Updated}, OrganizationMembership{Created,Updated,Removed}   (P1)
│   ├── {Project,Application,Environment}{Created,Updated,Archived,Restored}            (P2)
│   └── TokenCreated.php, TokenRevoked.php
├── Listeners/                 Reações; hoje, apenas auditoria
│   └── RecordAuditLog.php     Uma linha por evento Auditable
├── Http/
│   ├── Admin/AdminNavigation.php  Seções do admin e regra de entrada (Fase 4.2)
│   ├── Controllers/Api/V1/    Controllers REST versionados
│   │   ├── Auth/{AuthController, TokenController}.php
│   │   ├── MenuController.php
│   │   ├── Organization{,Membership}Controller.php       (P1)
│   │   ├── {Project,Application,Environment}Controller.php        (P2)
│   │   ├── {Project,Application,Environment}StatusController.php  archive/restore (P2)
│   │   ├── ProfileController.php
│   │   ├── UserController.php
│   │   ├── UserProfileController.php
│   │   └── UserStatusController.php   activate/deactivate (Fase 4.2)
│   ├── Controllers/Web/Admin/ Área administrativa Blade (Fases 4.1 a 4.4)
│   │   ├── Auth/LoginController.php
│   │   ├── HomeController.php
│   │   ├── User{,Profile,Status}Controller.php
│   │   ├── Profile{,Permission}Controller.php            (Fase 4.3)
│   │   ├── Menu{,Permission}Controller.php               (Fase 4.4)
│   │   └── Security/{Idor,MassAssignment}TestController.php   Security Lab (Fases 5.1 e 5.2)
│   ├── Middleware/
│   │   ├── CheckPermission.php            Permissão funcional por rota (404 JSON ou página)
│   │   ├── EnsureTokenAbility.php         Ability do token pelo método HTTP (403)
│   │   ├── EnsureUserIsActive.php         Encerra sessão web de inativo (grupo web)
│   │   ├── EnsureUserCanAccessAdmin.php   `admin.access`: entrada na área administrativa
│   │   ├── EnsureSecurityLabEnabled.php   `security.lab`: feature flag do laboratório (404)
│   │   └── ResolveOrganizationContext.php `organization.context`: tenant da rota, limpo em finally (P1)
│   ├── Requests/              FormRequests (namespace plano, Auth/ separado: Login, TokenStore)
│   │   └── Web/Admin/         Login web e requests de usuários, perfis e menus, que estendem os da API;
│   │                          Concerns/ com mensagens em português e SendsEveryField
│   └── Resources/             User, Profile, Menu, Organization, OrganizationMembership,
│                              Project, Application, EnvironmentResource
├── Exceptions/                LastActiveOrganizationOwnerException (409, P1)
├── Models/                    User, UserDetails, Profile, Menu, AuditLog,
│                              SecurityLabResource, SecurityTestRun,
│                              Organization, OrganizationMembership (P1),
│                              Project, Application, Environment (P2)
│                              + pivots UserProfile, MenuProfile
├── Policies/                  UserPolicy, ProfilePolicy, MenuPolicy,
│                              SecurityTestRunPolicy, SecurityLabResourcePolicy,
│                              Organization{,Membership}Policy (P1),
│                              {Project,Application,Environment}Policy (P2)
└── Providers/AppServiceProvider.php
bootstrap/app.php              Rotas, alias de middleware, exceções JSON
config/sanctum.php             Expiração (7 dias) e prefixo de token (napi_)
config/security.php            Feature flag do Security Lab (desligada por padrão)
database/
├── migrations/                citext, users, tokens, IAM, audit_logs, tenancy (P1),
│                              árvore de projetos com FK composta e triggers (P2)
├── factories/                 User, UserDetails, Profile, Menu, Organization,
│                              OrganizationMembership, Project, Application, Environment
└── seeders/                   Perfis, menus de sistema, usuários de demonstração
routes/api.php                 Todas as rotas /api/v1
routes/web.php                 Área administrativa /admin
resources/views/               layouts/admin(-shell), admin/** (Blade)
public/css/admin.css           CSS da área administrativa, sem build
tests/
├── Feature/IAM/               Testes HTTP (seed automático via tests/Pest.php)
├── Feature/Security/          Regressão de segurança com tokens Bearer reais (Fase 3.2) e cobertura de rotas
├── Feature/Admin/             Área administrativa por sessão web (Fases 4.1 a 4.4)
├── Feature/SecurityLab/       Security Lab: acesso, flag, testes IDOR e Mass Assignment, histórico (Fases 5.1 e 5.2)
├── Feature/Tenancy/           Organizations, memberships, isolamento e último owner (P1)
├── Feature/{Projects,Applications,Environments}/   HTTP + integridade no PostgreSQL (P2)
├── Unit/Domain/Tenancy/       Matriz papel × permission (P2)
└── Feature/{User,Profile,Menu}Test.php   Testes de model/constraints
```

## Regra de engenharia (permanente desde a Fase 5.1)

Antes de criar qualquer classe, tabela, coluna ou abstração, três perguntas
(e uma quarta no Security Lab):

1. **Esse código precisa existir?** Há uma responsabilidade real e
   independente que o justifique?
2. **Já tem algo no sistema que resolve?** Reutilizar ou estender antes de
   duplicar.
3. **Laravel, PHP ou PostgreSQL já resolvem?** Policy, FormRequest, enum,
   FK, CHECK, transação e paginação nativos antes de solução própria.
4. **No Security Lab: isso precisa ser vulnerável de verdade, ou dá para
   demonstrar dentro do runner controlado?** (desde a Fase 5.2) Código
   inseguro só dentro do caso de uso, só sobre dado sintético, e nenhum
   model, rota ou configuração real é enfraquecido para o teste funcionar.

O relatório de cada fase traz a tabela **Engineering Gate** com a resposta
por componente, inclusive para o que foi avaliado e **não** criado. A
primeira está em [phase-05-1-security-lab-idor.md](../phases/phase-05-1-security-lab-idor.md#engineering-gate).

**Generalizar só depois de dois casos concretos** (desde a Fase 5.2). Com o
segundo caso implementado e verde, e antes de refatorar, três perguntas:

1. **O que se repetiu?** Só repetição real, encontrada no código.
2. **O que merece abstração?** O que reduz duplicação relevante, tem
   semântica estável, simplifica a manutenção e não esconde diferença
   importante entre os casos.
3. **O que continua específico?**

A resposta entra no relatório da fase como **Abstraction Review** e orienta
a fase seguinte; a abstração não é criada na mesma fase em que aparece. A
primeira está em [phase-05-2](../phases/phase-05-2-security-lab-mass-assignment.md#abstraction-review).
Padrões são ferramentas: Action para caso de uso real, DTO para fronteira
real, evento quando há consumidor, e nada de Repository, Service, interface
ou hierarquia sem um problema concreto que eles resolvam.

## Convenções observadas

- **Enums nativos para conjuntos fechados** (`app/Domain/Security/Enums`),
  com cast no model e, no PostgreSQL, CHECK constraint com os mesmos valores.
- **Namespace de FormRequests plano** (`App\Http\Requests\ProfileStoreRequest`),
  com subnamespace só para autenticação (`Requests\Auth`). A Fase 3 manteve a
  convenção da Fase 2 em vez de criar subpastas por model, para não haver
  duas classes `UserStoreRequest` em namespaces diferentes.
- **Actions sob `app/Domain/{IAM,Security,Tenancy,Projects}`**, DTOs em `app/DTOs`. Actions expõem um
  método estático `execute()`. Não são resolvidas pelo container, portanto
  não recebem dependências injetadas.
- **Models com atributos PHP 8** (`#[Fillable]`, `#[Hidden]`) em vez de
  propriedades `$fillable`/`$hidden`.
- **Pivots com model próprio** quando há colunas extras tipadas:
  `UserProfile` (`assigned_by`, `assigned_at`) e `MenuProfile` (`can_*`). O
  `MenuProfile` foi criado na Fase 3 para o Larastan tipar `$menu->pivot->can_view`.
- **Rotas explícitas por verbo** em vez de `apiResource`, para que cada verbo
  receba seu próprio `permission:{route_name},{ação}`.
- **Tabelas tenant-aware** (desde o P2, ADR-0012): `organization_id`
  `NOT NULL`; referência ao pai, criador ou responsável por FK composta
  `(organization_id, coluna)` com colunas `NOT NULL`; `UNIQUE(organization_id, id)`
  quando a tabela for pai; trigger `napi_prevent_tenant_reassignment(...)`
  congelando tenant e pai; tenant e pai fora de `#[Fillable]`, definidos
  pela Action a partir da rota.
- **`Application` é o model de domínio.** A classe homônima do Laravel só
  aparece em `bootstrap/app.php`; um arquivo que precise das duas usa
  `use Illuminate\Foundation\Application as LaravelApplication;`.
- **Controller base** (`app/Http/Controllers/Controller.php`) usa
  `AuthorizesRequests`, habilitando `$this->authorize()`.

## Ferramentas de qualidade

| Ferramenta | Configuração | Script |
|---|---|---|
| Pest 4 | `tests/Pest.php`, `phpunit.xml` (PostgreSQL `napi_test`) | `composer test` |
| Pint | `pint.json` (`laravel`) | `composer lint` |
| Larastan | `phpstan.neon` (nível 6, `app/`, `parseModelCastsMethod` desde a Fase 5.1) | `composer analyse` |
