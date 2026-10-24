# Fase 4.3 — Perfis e matriz de permissões

[← Fases](README.md) · [Autorização](../security/authorization.md#área-administrativa-blade) · [Módulo IAM](../modules/iam.md) · [Fase 4.4](phase-04-4-menu-administration.md)

**Commit:** pending

Executada depois da [Fase 5.2](phase-05-2-security-lab-mass-assignment.md),
junto com a [4.4](phase-04-4-menu-administration.md). A numeração segue a
área (Administrador), não a ordem cronológica.

## Objetivo

Administrar perfis e a matriz perfil × menu pela área Blade, expondo com
segurança o modelo que o IAM já tinha desde a Fase 3.2: um perfil define
capacidades funcionais, a matriz as persiste, e as Policies e invariantes
continuam valendo por cima dela. Nada de modelo de permissão novo.

## Reality check

| Pergunta | Encontrado |
|---|---|
| Perfis e matriz já existiam? | Sim, na API: `ProfileController`, `SyncMenusRequest`, `ProfilePolicy::syncMenus` (anti-escalação), `SyncMenuPermissions` + `PermissionChanged` |
| A API gravava perfis por Action? | Não: `Profile::create($request->validated())` direto no controller |
| CRUD de perfil era auditado? | Não. Adiado desde a 3.2 ([FIND-012](../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria)) |
| Exclusão de perfil com usuários | `ProfilePolicy::delete` recusa, mas checa fora de transação, e `user_profiles` tem `cascade` |
| `Profile` aceita `is_system` em massa? | Sim, `#[Fillable]` inclui `is_system` (o seeder usa). As requests não aceitam o campo |
| A matriz da API grava booleanos? | Grava o que vier validado. Pelo admin viriam `"1"`/`"0"`, e o `meta` da auditoria divergiria entre canais |
| Seção de perfis no admin | Não existia em `AdminNavigation` |

## Engineering Gate

| Componente | Precisa existir? | Já existia? | Laravel/PHP/PostgreSQL resolvia? | Decisão |
|---|---|---|---|---|
| Matriz (regra, request, Policy, Action, evento) | Sim | `SyncMenusRequest`, `ProfilePolicy::syncMenus`, `SyncMenuPermissions`, `PermissionChanged` | — | **Reutilizados** sem regra nova |
| `SyncMenuPermissionsDto` | Sim | Sim | `filter_var(FILTER_VALIDATE_BOOLEAN)` | **Estendido**: normaliza as flags para booleano, nos dois canais |
| Actions `CreateProfile`, `UpdateProfile`, `DeleteProfile` | Sim: gravação e auditoria na mesma transação, compartilhadas pela API e pelo admin (critério do [ADR-0007](../architecture/decisions/ADR-0007-dto-action-boundary.md#evolução-na-fase-42)) | Não | `DB::transaction`, `lockForUpdate` | **Criadas**; a API passou a usá-las |
| DTOs `CreateProfileDto`, `UpdateProfileDto` | Sim: fixam os campos da operação; `is_system` está no `#[Fillable]` e não pode depender dele ([lição da 5.2](../security/mass-assignment.md)) | Padrão de `CreateUserDto`/`UpdateUserDto` | `readonly`, `Arr::only` | **Criados**; o de atualização filtra no construtor |
| Eventos `ProfileCreated`, `ProfileUpdated`, `ProfileDeleted` | Sim: um fato, uma linha de auditoria | Contrato `Auditable` | — | **Criados**, no padrão de `User*` |
| Listener | Não | `RecordAuditLog` | — | **Reutilizado** |
| `ResourceInUseException` | Sim: o recheck sob lock precisa de resposta 409 / flash | `LastActiveAdministratorException` mostra o padrão, mas é de outra regra | `DomainException` + `render()` | **Criada**, uma para perfil com usuários e menu com submenus |
| Policy nova | Não | `ProfilePolicy` | — | **Reutilizada** sem mudança |
| Requests web | Sim: mensagens em português, campos sempre enviados | Padrão da 4.2 (estendem os da API) | — | **Criadas** 3 (`Store`, `Update`, `SyncPermissions`) |
| Trait `SendsEveryField` | Sim: a terceira cópia da regra "edição exige todo campo" (usuários, perfis, menus) | Lógica inline em `UpdateUserRequest` | — | **Extraída** e reutilizada pelos três |
| Controllers web | Sim | Não | — | **Criados** `ProfileController` e `ProfilePermissionController`, finos |
| Contagem de permissões na lista | Sim | Não | Subconsulta com `sum(can_view::int + …)` | **Scope** `withPermissionCount`, uma consulta só |
| `Profile::matrix()` | Sim: a página pergunta à própria Policy se a matriz atual pode ser salva pelo ator | Não | `mapWithKeys` | **Criado** |
| Migration | Não: o schema já suporta perfis e matriz | — | — | **Não criada** |
| Service, Repository, pacote de RBAC, componente JS | Não | — | — | **Não criados** |

## Arquitetura

```text
Browser → web (sessão, CSRF, usuário ativo) → auth → admin.access
        → permission:profiles.* | permission:permissions.update   (404)
        → ProfileController / ProfilePermissionController
             → Store/UpdateProfileRequest | SyncPermissionsRequest (estendem os da API;
               authorize() → ProfilePolicy, incluindo syncMenus com anti-escalação)
             → DTO → CreateProfile | UpdateProfile | DeleteProfile | SyncMenuPermissions
                  DB::transaction: estado + evento Auditable → RecordAuditLog
        → redirect + flash
```

API e admin passam pelas mesmas Actions: `Api\V1\ProfileController` deixou
de gravar direto no model.

## Perfis

- **Lista** (`/admin/profiles`): nome, identificador, descrição, tipo
  (sistema ou personalizado), usuários, permissões concedidas (o
  Administrador aparece como "todas (implícito)"). 15 por página, contagens
  na mesma consulta.
- **Criação e edição**: nome, identificador (`slug`) e descrição. O perfil
  nasce personalizado e sem permissões. `is_system` não é campo de nenhum
  formulário nem dos DTOs.
- **Perfis de sistema** (`admin`, `dev`, `viewer`): `ProfilePolicy::update`
  e `delete` os recusam para todos, inclusive o Administrador. A página
  explica e não mostra as ações.
- **Exclusão**: só de perfil personalizado sem usuários (INV-05). A Policy
  decide; `DeleteProfile` confere de novo com a linha travada, porque
  `user_profiles` tem `cascade` e uma atribuição feita entre as duas
  checagens seria apagada em silêncio. O lock funciona porque inserir em
  `user_profiles` referencia a linha do perfil.

## Matriz de permissões

Fica no detalhe do perfil. Cada linha é uma área (menu, em ordem de
navegação, com a chave), cada coluna uma ação; cada célula é a permissão
`{chave}.{ação}`.

- **Salvar** usa `PUT /admin/profiles/{profile}/permissions`, com a mesma
  permissão (`permissions.update`), request, Policy e Action de
  `PUT /api/v1/profiles/{profile}/menus`. O formulário envia todas as
  células (um `"0"` oculto seguido do checkbox), e a sincronização substitui
  a matriz inteira, como na API.
- **Somente leitura** quando a própria `ProfilePolicy::syncMenus` diz que o
  ator não conseguiria salvar a matriz atual: perfil de sistema, perfil que
  o ator tem, falta de `permissions.update` ou flag concedida que o ator não
  tem.
- **Anti-escalação**: para quem não é Administrador, as células de
  permissões que ele não tem aparecem desabilitadas. É só aviso antecipado:
  quem decide é a Policy, e um envio forjado recebe 403 nos dois canais.
- **Pelo menu**: a mesma matriz também pode ser editada a partir de um
  menu (perfis × ações daquele menu), com a mesma Policy e Action; veja a
  [Fase 4.4](phase-04-4-menu-administration.md#perfis-de-um-menu). A edição
  do perfil continua só com nome, identificador e descrição, e aponta para a
  matriz no detalhe.
- **Administrador**: a página diz que ele tem todas as permissões
  implicitamente, inclusive das áreas criadas depois. A matriz não se aplica
  a ele, e isso não dispensa Policies nem invariantes.

## Autorização

```text
sessão web + usuário ativo
  → admin.access (ao menos uma seção visível; Perfis agora conta)
  → permissão funcional (profiles.* ou permissions.update, 404)
  → Policy (ProfilePolicy, 403)
  → invariante de domínio (DeleteProfile sob lock, 409 / flash)
```

A seção **Perfis** aparece para quem tem `profiles.view`
(`ProfilePolicy::viewAny`). Visibilidade não é autorização: as rotas mantêm
`permission:` e Policy.

## Auditoria

| Ação | Evento | Meta |
|---|---|---|
| `profile_created` | `ProfileCreated` | — |
| `profile_updated` | `ProfileUpdated` | `fields` (nomes, sem valores); atualização sem mudança não registra nada |
| `profile_deleted` | `ProfileDeleted` | `slug` e `name`, porque a linha deixa de existir |
| `permission_changed` | `PermissionChanged` (reutilizado) | matriz final, com booleanos nos dois canais |

## Testes

| Arquivo | Testes |
|---|---|
| `tests/Feature/Admin/ProfileManagementTest.php` (novo) | 26 |
| `tests/Feature/Security/AuditIntegrityTest.php` | +2 (CRUD de perfil pela API auditado uma vez cada; nada registrado em mudança recusada) |
| `tests/Feature/Admin/UserManagementTest.php` | 1 alterado (veja abaixo) |

O que cada um garante está em [security-tests.md](../testing/security-tests.md#administração-de-perfis-e-menus-fases-43-e-44).

### Testes antigos alterados

| Teste | Antes | Depois | Motivo |
|---|---|---|---|
| `UserManagementTest › does not count permissions the admin area has no section for` | usava `menus.view` como permissão sem seção | usa `audit-logs.view` | Menus e Perfis agora são seções do admin |
| `RouteCoverageTest › keeps guests and users without admin access out of every internal admin route` | substituía só `{user}` na URL | substitui `{user}`, `{profile}` e `{menu}` | rotas novas com parâmetro |

## Findings

| Finding | Na Fase 4.3 |
|---|---|
| [FIND-012](../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria) | CRUD de perfil passa a ser auditado (o de menu, na 4.4). Resta o login falho, ainda adiado |

## Fora do escopo

Atribuição de perfis a usuários (já existe no detalhe do usuário, Fase 4.2);
cópia de perfis; matriz por usuário; viewer de `audit_logs`.

## Revisão: perfis de sistema geridos pelo Administrador

Pedido depois da entrega, para testes e atribuições. Decisão registrada no
[adendo do ADR-0008](../architecture/decisions/ADR-0008-layered-authorization-model.md#adendo-fase-44-revisão).

| Antes | Depois |
|---|---|
| Perfil de sistema imutável para todos, inclusive o Administrador | Só o Administrador edita, exclui (sem usuários) e altera a matriz |
| `is_system` nunca aceito na entrada | Aceito só do Administrador, na criação e na edição; 422 para os demais, na API e no admin |
| Perfil `admin` imutável | Nome e descrição editáveis pelo Administrador; identificador fixo, sempre de sistema, sem matriz, nunca excluído |

| Componente | Decisão |
|---|---|
| `ProfilePolicy` | `reachable()`: perfil de sistema só para o Administrador; `syncMenus` recusa o perfil `admin` para todos |
| `ProfileStore/UpdateRequest` | `is_system` com `prohibited_if` (não-Administrador, ou perfil `admin`); `slug` `prohibited` no perfil `admin` |
| `CreateProfileDto`, `UpdateProfileDto`, `CreateProfile` | carregam `is_system` |
| `UpdateProfile` | `LogicException` se algo tentar mudar o identificador do perfil `admin` ou desmarcá-lo como sistema |
| Formulário | caixa "Perfil de sistema" só para o Administrador; identificador do perfil `admin` como texto |
| `ProfileDeleted` | `meta.is_system` |
| Regra própria para não excluir o perfil `admin` | **Não criada**: ele sempre tem usuário ativo (INV-08), e perfil com usuários não é excluído. Uma checagem a mais seria código que nunca decide |

Testes: `ProfileManagementTest` passou a cobrir o Administrador editando,
desmarcando e excluindo perfis de sistema, a recusa para quem não é
Administrador nos dois canais, o identificador fixo do perfil `admin` (request
e caso de uso) e a matriz de perfis de sistema; `PrivilegeEscalationTest`
ganhou `keeps system profiles out of reach of delegates`.
