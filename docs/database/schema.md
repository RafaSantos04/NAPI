# Schema

[← Banco de dados](README.md) · [IAM](../modules/iam.md) · [ADR-0002](../architecture/decisions/ADR-0002-postgresql.md)

Fonte: `database/migrations/*`. Só estão listadas as tabelas do domínio. As
tabelas padrão do framework (`cache`, `jobs`, `sessions`,
`password_reset_tokens`) existem, mas não são usadas pela API hoje.

## Diagrama

```mermaid
erDiagram
    users ||--o| user_details : "user_id UNIQUE, cascade"
    users ||--o{ user_profiles : "user_id, cascade"
    profiles ||--o{ user_profiles : "profile_id, cascade"
    users |o--o{ user_profiles : "assigned_by, set null"
    profiles ||--o{ menu_profiles : "profile_id, cascade"
    menus ||--o{ menu_profiles : "menu_id, cascade"
    menus |o--o{ menus : "parent_id, cascade"
    users |o--o{ audit_logs : "user_id, set null"
    users ||--o{ security_lab_resources : "owner_user_id, cascade"
    users |o--o{ security_test_runs : "initiated_by_user_id, set null"
    users |o--o{ security_test_runs : "acting_as_user_id, set null"
    security_lab_resources |o--o{ security_test_runs : "target_resource_id, set null"
    users ||--o{ organization_memberships : "user_id, restrict"
    organizations ||--o{ organization_memberships : "organization_id, restrict"
    organizations |o--o{ audit_logs : "organization_id, set null"
    organizations ||--o{ projects : "organization_id, restrict"
    organization_memberships ||--o{ projects : "(organization_id, created_by_membership_id), restrict"
```

## Tabelas

### `users`

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | ULID | PK |
| `name` | varchar | |
| `email` | **citext** | UNIQUE (sem distinção de maiúsculas) |
| `email_verified_at` | timestamp | nullable |
| `password` | varchar | hash bcrypt (cast `hashed`) |
| `is_active` | boolean | default `true` |
| `created_at`, `updated_at`, `deleted_at` | timestamp | soft delete |

A migration cria a coluna como `string` e depois executa
`ALTER COLUMN email TYPE citext`, porque o Schema Builder não tem o tipo.

### `user_details`

Um por usuário (`user_id` UNIQUE, FK cascade). Dados pessoais e LGPD:
`cpf_hash` (UNIQUE, nullable), telefone, endereço brasileiro (`cep`, `state`
com 2 caracteres, `country` default `BR`), `data_consent_at`,
`data_export_requested_at`, `data_deletion_requested_at`. Não é exposto pela
API. Veja [FIND-008](../findings/README.md#find-008--hash-de-cpf-é-reversível-por-força-bruta).

### `profiles`

`id` ULID PK · `name` varchar(100) UNIQUE · `slug` varchar(100) UNIQUE ·
`description` text · `is_system` boolean default `false` · timestamps.

### `user_profiles` (pivot → `UserProfile`)

| Coluna | Tipo | Constraint |
|---|---|---|
| `user_id` | ULID | PK composta; FK `users` cascade |
| `profile_id` | ULID | PK composta; FK `profiles` cascade |
| `assigned_by` | ULID | nullable; FK `users` **set null** |
| `assigned_at` | timestamp | default `CURRENT_TIMESTAMP`; indexado |

A PK composta impede atribuir o mesmo perfil duas vezes.

### `menus`

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | ULID | PK |
| `parent_id` | ULID | nullable; FK auto-referente `menus` cascade; indexado; CHECK `parent_id <> id` (Fase 4.4) |
| `key` | varchar(100) | NOT NULL, UNIQUE; **identificador de permissão** (`{key}.{ação}`), imutável pela API (Fase 3.2) e pelo admin (Fase 4.4) |
| `label` | varchar(100) | |
| `route_name` | varchar(100) | UNIQUE; só navegação desde a Fase 3.2 |
| `icon` | varchar(50) | nullable |
| `order` | integer | default 0; indexado |
| `is_active` | boolean | default `true`; só navegação, não afeta autorização |
| `is_system` | boolean | default `false`; menus-chave, só o Administrador exclui (Fase 3.2; revisão da Fase 4.4) |
| `description` | text | nullable |

`key` e `is_system` foram adicionadas na Fase 3.2 diretamente na migration
de criação (`2026_09_20_005741_create_menus_table`), e não numa migration
nova. Como o projeto ainda não tem banco de produção, não havia dados a
migrar, e um backfill seria código sem uso. Consequência: bancos criados
antes da Fase 3.2 precisam de `php artisan migrate:fresh --seed`. Quando
houver produção, mudanças de schema voltam a ser migrations aditivas.

`menus_parent_not_self_check` (Fase 4.4, migration
`2026_10_20_013946_add_parent_not_self_check_to_menus_table`) recusa um menu
como pai de si mesmo. Ciclos mais longos envolvem outras linhas, que um CHECK
não enxerga; são recusados pela validação (`MenuUpdateRequest`), com uma
consulta recursiva (`WITH RECURSIVE … UNION`). Ver
[FIND-019](../findings/README.md#find-019--menu-pode-ser-pai-de-si-mesmo).

A FK de `parent_id` é criada em um segundo `Schema::table`. O comentário na
migration explica que, no PostgreSQL, a PK do ULID é adicionada por um
`ALTER TABLE` separado que poderia rodar depois de uma FK inline.

### `menu_profiles` (pivot → `MenuProfile`): a matriz de permissões

| Coluna | Tipo | Constraint |
|---|---|---|
| `menu_id` | ULID | PK composta; FK cascade |
| `profile_id` | ULID | PK composta; FK cascade |
| `can_view`, `can_create`, `can_update`, `can_delete` | boolean | **default `false`** (deny by default) |

Sem timestamps: não há histórico de quando uma permissão mudou fora de
`audit_logs`.

### `audit_logs`

`id` ULID PK · `user_id` ULID FK **set null** · `organization_id` ULID
nullable FK **set null** (P1) · `action` varchar(50) ·
`subject_type` varchar(100) · `subject_id` ULID · `ip` **inet** · `user_agent`
text · `meta` **jsonb** · `created_at` default `CURRENT_TIMESTAMP`. Índices
`(user_id, created_at)` e `(subject_type, subject_id)`. Sem `updated_at`.
Veja [audit.md](../security/audit.md).

Desde P1, o trigger `audit_logs_append_only` recusa `UPDATE` e `DELETE`; o
model também recusa as duas operações. Inserts do listener continuam
permitidos. O índice `(organization_id, created_at)` sustenta a trilha por
tenant.

### `organizations` (P1)

`id` ULID PK · `name` varchar(100) · `slug` varchar(100) UNIQUE · timestamps.

Organization é o boundary do tenant. P1 não adiciona status, billing,
deleção nem soft delete.

### `organization_memberships` (P1)

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | ULID | PK |
| `organization_id` | ULID | FK `organizations` **restrict**; imutável (trigger, P2) |
| `user_id` | ULID | FK `users` **restrict**; imutável (trigger, P2) |
| `role` | varchar(20) | CHECK `owner` \| `manager` \| `member` (`manager` desde o P2) |
| `status` | varchar(20) | CHECK `active` \| `disabled`; default `active` |
| `created_at`, `updated_at` | timestamp | |

UNIQUE `(organization_id, user_id)` impede memberships duplicadas. Índices
`(user_id, status)` e `(organization_id, status, role)` atendem listagem do
usuário, permissão contextual e invariante do owner. Remoção lógica do
vínculo usa `status = disabled`; não há soft delete.

Desde o P2, `UNIQUE(organization_id, id)` é o alvo das FKs compostas que
prendem um registro a uma membership da própria organização, e o trigger
`organization_memberships_tenant_immutable` recusa `UPDATE` de
`organization_id` e `user_id` ([ADR-0012](../architecture/decisions/ADR-0012-project-tree-tenant-integrity.md)).

### `projects` (P2)

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | ULID | PK |
| `organization_id` | ULID | FK `organizations` **restrict**; imutável (trigger) |
| `created_by_membership_id` | ULID | `NOT NULL`; FK composta `(organization_id, created_by_membership_id)` → `organization_memberships(organization_id, id)` **restrict**; imutável (trigger) |
| `name` | varchar(100) | |
| `slug` | varchar(100) | UNIQUE `(organization_id, slug)` |
| `description` | text | nullable |
| `archived_at` | timestamp | nullable; `null` = ativo |
| `created_at`, `updated_at` | timestamp | |

A FK composta impede, mesmo por SQL direto, um project cujo criador seja
membership de outra organização. `UNIQUE(organization_id, id)` é o alvo da FK
composta de `applications`. O trigger `projects_tenant_immutable` congela
tenant e criador. Não há soft delete nem DELETE pela API; arquivar preenche
`archived_at` e deixa a árvore somente leitura.

### `personal_access_tokens` (Sanctum)

`id` **bigint** PK · `tokenable_type` + `tokenable_id` (`ulidMorphs`,
indexados) · `name` text · `token` varchar(64) UNIQUE (hash SHA-256) ·
`abilities` text · `last_used_at` · `expires_at` (indexado) · timestamps.

### `security_lab_resources` (Fases 5.1 e 5.2)

Documentos sintéticos do [Security Lab](../modules/security.md). Existem para
que nenhuma tabela real seja alvo de um cenário vulnerável. O mesmo
documento é lido pelo teste IDOR e escrito pelo teste Mass Assignment.

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | ULID | PK |
| `owner_user_id` | ULID | FK `users` cascade (persona dona) |
| `name` | varchar(100) | UNIQUE com `owner_user_id` (também é o índice por dono) |
| `content` | text | começa com `Synthetic Security Lab Data` no seed |
| `is_approved` | boolean | NOT NULL, default `false` (Fase 5.2). Propriedade protegida do teste [Mass Assignment](../security/mass-assignment.md) |
| `created_at`, `updated_at` | timestamp | |

`is_approved` não tem CHECK nem índice: o tipo e o default são toda a regra
de integridade, e nada consulta por ela. A coluna entrou por migration
própria (`add_is_approved_to_security_lab_resources_table`), reversível.

### `security_test_runs` (Fase 5.1)

Uma linha por execução de teste do laboratório. Só inserção, como
`audit_logs`: `created_at` vem do banco e não há `updated_at`.

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | ULID | PK |
| `test_key` | varchar(50) | identidade estável do teste (`idor`, `mass_assignment`); o rótulo é apresentação |
| `scenario` | varchar(20) | CHECK `vulnerable` \| `protected` |
| `initiated_by_user_id` | ULID | nullable; FK `users` **set null**; indexado. O operator |
| `acting_as_user_id` | ULID | nullable; FK `users` **set null**; indexado. O actor simulado (no Mass Assignment, o dono do alvo) |
| `target_resource_id` | ULID | nullable; FK `security_lab_resources` **set null**; indexado |
| `observed_outcome` | varchar(20) | nullable; CHECK `allowed` \| `denied`. O pedido foi atendido como feito, ou não |
| `security_verdict` | varchar(20) | CHECK `exposed` \| `protected` \| `inconclusive` |
| `execution_status` | varchar(20) | CHECK `completed` \| `error` |
| `result_context` | **jsonb** | nullable; dado específico do teste, ou `error` (veja abaixo) |
| `created_at` | timestamp | default `CURRENT_TIMESTAMP` |

Índice `(test_key, created_at)` para o histórico de um teste.

`result_context` por teste:

| `test_key` | Chaves | Por quê |
|---|---|---|
| `idor` | `target_owner_id` | o alvo pode ser excluído depois, e a execução ainda precisa dizer de quem era |
| `mass_assignment` | `attempted` (nomes das propriedades enviadas), `before` e `after` (`name` e `is_approved`), `protected_changed` | o documento é restaurado ao fim da execução; o registro é o único lugar que ainda sabe o que a escrita fez |
| qualquer, com falha | `error` (nome da classe da exceção) | sem mensagem nem stack trace |

A Fase 5.2 não mudou `security_test_runs`: o segundo teste coube na tabela,
nas constraints e nos índices da 5.1.

Duas CHECK constraints mantêm os três fatos coerentes:

- `(execution_status = 'completed') = (observed_outcome IS NOT NULL)`: nada é
  observado quando o runner falha;
- `execution_status = 'completed' OR security_verdict = 'inconclusive'`: uma
  falha nunca afirma um resultado de segurança.

Colunas avaliadas e **não** criadas, por serem redundantes ou deriváveis:
`target_type` (a FK já fixa o tipo e garante que o alvo é sintético),
`expected_outcome` (derivável do dono do alvo, guardado em `result_context`),
`input_context` (actor, alvo e cenário já são colunas), `duration_ms`,
`started_at`, `finished_at` e `updated_at` (a execução é síncrona e
imutável; `created_at` basta). Contadores agregados também não existem: saem
de consultas sobre esta tabela.

## Onde o PostgreSQL participa da arquitetura

| Recurso | Uso | Efeito |
|---|---|---|
| `citext` | `users.email` | Unicidade case-insensitive no banco (teste `email is case-insensitive (citext)`) |
| ULID (`char(26)`) | PKs de domínio | IDs não sequenciais e ordenáveis por tempo |
| `jsonb` | `audit_logs.meta`, `security_test_runs.result_context` | Metadado variável por ação ou por teste, consultável |
| `CHECK` | `security_test_runs` (conjuntos fechados e coerência entre status, resposta e veredito) | O banco recusa uma execução com falha que afirme proteção (testes de constraint) |
| `inet` | `audit_logs.ip` | IP validado pelo tipo |
| PKs compostas | pivots | Impedem vínculos duplicados |
| `cascade` | pivots, `user_details`, `menus.parent_id`, `security_lab_resources.owner_user_id` | Excluir perfil ou menu remove vínculos e permissões (testes de force delete) |
| `set null` | `audit_logs.user_id`, `user_profiles.assigned_by`, FKs de `security_test_runs` | Trilha e histórico sobrevivem à exclusão do ator (teste) |
| `UNIQUE` | `email`, `profiles.name/slug`, `menus.key`, `menus.route_name`, `cpf_hash`, `token` | Última linha de defesa contra duplicidade (testes de validação) |
| `SELECT … FOR UPDATE` | linha do perfil `admin` | Serializa as remoções de administrador; a invariante "≥ 1 admin ativo" não depende de check-then-act sem proteção (INV-08) |
| Defaults booleanos | `menu_profiles.can_*` | Negação por padrão garantida no schema |
| CHECKs de membership | role e status | recusam estados fora do lifecycle de P1 |
| UNIQUE composto | `organization_memberships` | um vínculo por User e Organization |
| `SELECT … FOR UPDATE` | linha da Organization | serializa remoção/demote/disable de owners diferentes |
| Trigger | `audit_logs` | trilha append-only mesmo sem Eloquent |
| Trigger `napi_prevent_tenant_reassignment()` | `organization_memberships`, `projects` (P2); demais tabelas da árvore nos próximos slices | congela tenant e pai: a FK composta valida o INSERT, o trigger impede mover a linha depois |
| FK composta | `projects` → `organization_memberships` (P2) | criador sempre da mesma organização |

P1 usa `restrict` nas duas FKs de `organization_memberships`, porque um
vínculo histórico não pode desaparecer pela exclusão do User ou da
Organization. Nos pivots legados de perfis e menus permanece `cascade`: a
Policy e as Actions decidem a exclusão e o banco remove somente os vínculos
dependentes depois que ela foi autorizada.
