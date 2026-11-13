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
usuários criados são de **demonstração**, restritos a `local` e `testing`.
A senha vem de `NAPI_SEED_USER_PASSWORD`, precisa ter ao menos 12 caracteres
e no máximo 72 bytes e não tem fallback; ausente ou fora desse intervalo, o
seeder aborta antes de criar
qualquer usuário. O valor preenchido fica apenas no `.env`, nunca no Git.
O `UserSeeder` é idempotente: executá-lo novamente rotaciona o hash e restaura
o perfil esperado das três contas, sem duplicá-las.
Conteúdo semeado em
[iam.md](../modules/iam.md#estado-semeado-demonstração).
