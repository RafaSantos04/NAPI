# ADR-0002 — PostgreSQL com ULIDs, citext, jsonb e integridade no banco

[← ADRs](README.md) · [Schema](../../database/schema.md)

## Status

Accepted (registrado retroativamente na Fase 3.1)

## Context

O IAM tem relações muitos-para-muitos com atributos (usuário ↔ perfil,
perfil ↔ menu com flags de permissão), hierarquia de menus, trilha de
auditoria com metadados variáveis e identificadores expostos em URLs.

## Decision

- Banco de dados **PostgreSQL**, inclusive nos testes (`phpunit.xml` usa
  `napi_test` em `pgsql`, não SQLite).
- **ULID** como chave primária de todas as tabelas de domínio (`users`,
  `profiles`, `menus`, `audit_logs`, `user_details`) via `HasUlids`.
- Extensão **`citext`** para `users.email`.
- **`jsonb`** para `audit_logs.meta`.
- **Integridade referencial no banco**: FKs com `cascade` nos pivots e em
  `menus.parent_id`, e `set null` em `audit_logs.user_id` e
  `user_profiles.assigned_by`.

## Rationale

Não há evidência da motivação histórica para PostgreSQL em si. Há evidência
para decisões pontuais:

- `citext`: o teste `email is case-insensitive (citext)` e a migration
  `0000_00_00_000000_enable_citext_extension.php` mostram que a unicidade de
  e-mail sem distinção de maiúsculas é garantida pelo banco, e não por
  normalização na aplicação.
- FK de `menus.parent_id` criada em um segundo `Schema::table`: o comentário
  na migration explica um problema de ordenação de constraints do PostgreSQL.
- Testes contra PostgreSQL: a mensagem do commit `01da095` ("pgsql test db")
  indica a intenção de testar contra o mesmo motor de produção.

Propriedades que o desenho proporciona:

- ULIDs não são sequenciais nem adivinháveis como inteiros. Isso dificulta
  enumeração de recursos por ID, **mas não substitui autorização**. Como são
  ordenáveis por tempo, também evitam a fragmentação de índice típica de UUIDv4.
- `set null` em `audit_logs.user_id` preserva a trilha mesmo após
  `forceDelete` do usuário (há teste para isso).
- `jsonb` permite metadados de auditoria de formato variável por ação,
  consultáveis e indexáveis no futuro.

## Alternatives Considered

Não documentadas. SQLite em testes, padrão do Laravel, foi evitado: testes
com `citext` e FKs de verdade exigem PostgreSQL.

## Consequences

### Positive

- Constraints são a última linha de defesa. Mesmo um bug na aplicação não
  cria e-mail duplicado com outra caixa, pivot órfão ou perfil com slug
  repetido.
- Os testes exercitam o mesmo SQL de produção.

### Negative

- `personal_access_tokens.id` continua `bigint` sequencial (padrão do
  Sanctum). Não há IDOR, porque a revogação filtra pelo dono, mas é a única
  PK sequencial exposta na API.
- Rodar os testes exige um PostgreSQL local.
- Cascades em `menus.parent_id` e nos pivots significam que excluir um menu
  **revoga silenciosamente** as permissões ligadas a ele
  ([FIND-005](../../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema)).

## Security Impact

- Unicidade de e-mail case-insensitive evita contas duplicadas como
  `Admin@` e `admin@`.
- A trilha de auditoria sobrevive à exclusão do autor.
- O banco não impede UPDATE ou DELETE em `audit_logs`
  ([FIND-014](../../findings/README.md#find-014--audit_logs-é-mutável)).

## References

- `database/migrations/*`
- `tests/Feature/UserTest.php`, `tests/Feature/ProfileTest.php`, `tests/Feature/MenuTest.php`
- `phpunit.xml`
