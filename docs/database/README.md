# Banco de dados

[← Documentação](../README.md) · [ADR-0002](../architecture/decisions/ADR-0002-postgresql.md)

- [schema.md](schema.md): tabelas, chaves, constraints, índices e o papel do
  PostgreSQL em cada decisão.

## Ambientes

| Ambiente | Banco | Fonte |
|---|---|---|
| Desenvolvimento | `napi` | `.env.example` |
| Testes | `napi_test` | `phpunit.xml` |

Ambos exigem a extensão `citext`, habilitada pela primeira migration
(`0000_00_00_000000_enable_citext_extension.php`).

Os testes usam `RefreshDatabase`, e os testes em `tests/Feature/IAM` rodam
`DatabaseSeeder` antes de cada caso (`tests/Pest.php`).

## Seeders

`DatabaseSeeder` → `ProfileSeeder` → `MenuSeeder` → `UserSeeder`. A ordem
importa: menus e usuários buscam perfis por slug com `firstOrFail()`. Os
usuários criados são de **demonstração**, com senha fraca definida no
seeder, e não devem ser semeados em produção. Conteúdo semeado em
[iam.md](../modules/iam.md#estado-semeado-demonstração).
