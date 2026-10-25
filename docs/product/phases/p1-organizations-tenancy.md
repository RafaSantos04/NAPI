# P1 — Organizations, Memberships & Tenancy

[← Produto](../README.md) · [Roadmap](../roadmap.md) · [ADR-0011](../../architecture/decisions/ADR-0011-multi-tenant-organization-context.md)

**Status:** implementada e validada · **Commit:** pending

## Executive Summary

P1 introduz o primeiro boundary comercial do NAPI sem duplicar identidade
nem IAM. `User` continua global; `Organization` é o tenant; uma
`OrganizationMembership` ativa dá acesso contextual. A API identifica a
organização pela rota e estabelece um contexto somente durante a requisição.
Esse contexto não autoriza: `User::hasPermission()` consulta a membership,
a Policy decide sobre o recurso e a Action protege invariantes.

O backoffice Blade e o Security Lab continuam globais. Nenhum conceito de
P2 foi criado.

## Reality Check

Antes de P1 já existiam autenticação global, abilities do Sanctum, matriz
perfil × menu, Policies, Actions, auditoria síncrona e dois adapters. Não
existiam Organization, membership, tenant context nem qualquer coluna de
tenant. O `User::hasPermission()` aceitava somente permissões globais.

P1 reutiliza a arquitetura do ADR-0008 em vez de criar `OrganizationRole`,
`OrganizationPermission` e uma matriz configurável em tabelas paralelas. Os
dois enums de lifecycle e o catálogo fechado de quatro permissions existem
porque o banco e as Policies precisam de conjuntos explícitos e tipados.

## Engineering Gate

| Componente | Precisa existir? | Já existia? | Laravel/PHP/PostgreSQL resolve? | Decisão |
|---|---|---|---|---|
| Organization | sim, boundary do tenant | não | Eloquent + ULID | criada com nome, slug e timestamps |
| Membership | sim, vínculo com lifecycle e regra de owner | não | model + FKs/UNIQUE/CHECK | entidade real criada |
| Organization Context | sim, tenant explícito e testável | não | binding + container scoped | classe mínima request-scoped |
| Policy | sim, decisão contextual | padrão existente | Laravel Gate/Policy | duas Policies criadas |
| Middleware | sim, uma resolução por rota | `CheckPermission` existia | route model binding | resolver criado; permission middleware evoluído |
| Action | sim, transação/auditoria/invariante | padrão existente | `DB::transaction` | cinco casos de uso criados |
| DTO | sim nas quatro fronteiras com payload | padrão existente | readonly + FormRequest | quatro DTOs criados |
| Event | sim, trilha atômica | `Auditable` + listener | event discovery | cinco eventos criados |
| Migration | sim, schema e auditoria | — | FK, UNIQUE, CHECK, trigger | quatro migrations aditivas |
| Global Scope | não | não | scopes explícitos bastam | rejeitado no ADR-0011 |
| Service/Repository | não | Eloquent + Actions | container/relations | não criados |
| Enum | sim para sets fechados | não | backed enums + CHECK | role, status e permission |
| SoftDeletes | não | User já usa | status preserva membership | não aplicado |

## Data Model

```text
User (global)
  ↓ 1:N
OrganizationMembership
  ↓ N:1
Organization (tenant)
```

`organizations`: ULID, nome, slug único e timestamps.

`organization_memberships`: ULID, FKs obrigatórias, role `owner|member`,
status `active|disabled`, timestamps, UNIQUE `(organization_id, user_id)` e
índices para acesso do usuário e contagem de owners.

Organizations não são excluídas/arquivadas em P1. Remover membro significa
desabilitar a membership; o registro permanece e pode ser reativado.

## Organization Context

### API

O parâmetro `/organizations/{organization}` é resolvido pelo binding. Em
seguida `ResolveOrganizationContext` preenche `OrganizationContext`; ao fim,
um `finally` limpa o estado para impedir vazamento em testes ou processos
long-lived. Trocar de organização é trocar a rota da próxima requisição.

### Web

`/admin` continua backoffice interno e não recebe tenant context. Não há
workspace Blade nem seleção em sessão. P3 decidirá o adapter do Vue.

## Authorization

```text
authenticated User
      ↓
Organization Context (identifica)
      ↓
active Membership (concede contexto)
      ↓
User::hasPermission(permission, organization)
      ↓
Policy (recurso e tenant)
      ↓
Action / invariant
```

Owner recebe `organizations.view`, `organizations.update`, `members.view` e
`members.manage`. Member recebe somente as duas leituras. Controllers não
testam role. O administrador global não ignora membership.

Criar/listar organizações é autoatendimento autenticado; não há tenant antes
da criação. Todas as rotas sob uma Organization exigem contexto e permission.

## Tenant Isolation

- listagem parte de memberships ativas do usuário;
- contexto vem do model binding da rota, nunca do payload;
- permission middleware usa o mesmo contexto que as Policies;
- membership aninhada usa scoped binding e a Policy reconfirma o parent;
- UNIQUE/FKs/CHECKs protegem integridade fora do HTTP;
- testes cobrem id de organização e membership manipulados, payload válido,
  token válido, admin global e membership disabled.

P1 não usa Global Scope. Futuros agregados carregam `organization_id` e
devem usar queries/scopes explícitos mais essas mesmas camadas.

## Membership Lifecycle

```text
active ⇄ disabled
```

Somente usuários globais existentes, ativos e não excluídos entram por
POST. Convite por endereço e estado `invited` foram adiados para não criar
um lifecycle incompleto sem token, expiração, aceite e e-mail.

DELETE desabilita; PATCH pode mudar role/status e reativar. Uma membership
disabled perde acesso imediatamente, mas tokens e login globais continuam.

## Last Owner Invariant

Owner válido = membership `owner` + `active` + User ativo e não excluído.
Antes de remover, desabilitar ou rebaixar um owner, a Action trava a linha da
Organization e a membership nessa ordem e verifica outro owner válido. A
mesma regra roda antes de desativar ou excluir globalmente um User.

A Organization é o ponto comum de lock: duas operações sobre memberships
diferentes não conseguem ambas observar “outro owner” e removê-lo.

## Audit

`audit_logs.organization_id` é nullable, tem FK `SET NULL` e índice com
`created_at`. Eventos globais/Lab continuam nulos; eventos de P1 gravam o
tenant, ator e subject. São registrados:

- `organization_created`, `organization_updated`;
- `membership_created`, `membership_updated`, `membership_removed`.

FIND-014 foi resolvido: o model recusa update/delete e um trigger do
PostgreSQL bloqueia mutação que ignore Eloquent. O banco continua permitindo
INSERT pelo listener.

## Threat Model

| Ameaça | Mitigação P1 |
|---|---|
| Cross-tenant IDOR | contexto explícito + membership + permission + Policy + scoped binding |
| Tenant context tampering | id só identifica; nunca autoriza |
| Membership privilege escalation | `members.manage`, role encapsulada e Policy |
| Organization sem owner | lock da Organization + recontagem transacional |
| Stale context | middleware limpa em `finally`; membership é consultada a cada decisão |
| Leakage em jobs | regra de carregar organization id e criar contexto novo; nenhum job tenant em P1 |
| Leakage em auditoria | coluna explícita e consultas futuras por organization |

## Tests e mutações

`tests/Feature/Tenancy/` cobre Organizations, memberships, isolamento e a
invariante. `RouteCoverageTest` exige contexto e permission em toda rota
tenant-aware.

Mutações executadas e revertidas:

1. remover `whereBelongsTo($organization)` de `User::hasPermission()` fez o
   teste de id trocado receber 200 em vez de 404;
2. remover a chamada da invariante em `RemoveOrganizationMember` fez o último
   owner ser removido com 200 em vez de 409.

## Findings

- FIND-014: **RESOLVED** por model guard + trigger PostgreSQL.
- FIND-016 (rate limiting global): continua aberto; P1 não aumenta endpoints
  caros nem tenta resolver o limiter de toda a API.
- Convites externos: deferred, por decisão de escopo, sem representação
  enganosa no banco.

## Quality

Resultado final: `composer test` passou com **424 testes e 1.785
assertions**. Migrações P1 também passaram por rollback e reaplicação. Os
demais comandos do gate estão registrados no fechamento da fase e em
[testing/README](../../testing/README.md): `composer lint`, `composer
analyse` e `composer validate`.

