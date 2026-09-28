# Estratégia de testes

[← Documentação](../README.md) · [Testes de segurança](security-tests.md)

## Configuração

- **Pest 4** sobre PHPUnit 12 (`composer test`).
- **Banco real**: PostgreSQL `napi_test` (`phpunit.xml`), não SQLite, para
  exercitar `citext`, FKs e cascades de verdade
  ([ADR-0002](../architecture/decisions/ADR-0002-postgresql.md)).
- `RefreshDatabase` em todo `tests/Feature` (`tests/Pest.php`).
- `tests/Feature/IAM/*` roda o `DatabaseSeeder` antes de cada teste. O
  escopo é limitado a esse diretório para não colidir com testes que criam o
  próprio perfil `admin` via factory (comentário em `tests/Pest.php`).
- `Model::shouldBeStrict()` fica ativo nos testes: lazy loading e mass
  assignment indevidos quebram a suíte.
- Qualidade complementar: `composer lint` (Pint) e `composer analyse`
  (Larastan nível 6 em `app/`).

## Inventário (53 testes)

| Categoria | Arquivo | Testes | Nível |
|---|---|---|---|
| Authentication | `Feature/IAM/AuthenticationTest.php` | 6 | HTTP |
| Authorization | `Feature/IAM/AuthorizationTest.php` | 6 | HTTP + Policy direta |
| Profiles | `Feature/IAM/ProfileTest.php` | 6 | HTTP |
| Menus | `Feature/IAM/MenuTest.php` | 4 | HTTP |
| Users ↔ Profiles | `Feature/IAM/UserProfileSyncTest.php` | 4 | HTTP + evento |
| Tokens | `Feature/IAM/TokenManagementTest.php` | 3 | HTTP |
| Audit | `Feature/IAM/AuditEventTest.php` | 2 | HTTP + banco |
| Model/DB: Users | `Feature/UserTest.php` | 10 | Eloquent + constraints |
| Model/DB: Profiles | `Feature/ProfileTest.php` | 5 | Eloquent + constraints |
| Model/DB: Menus | `Feature/MenuTest.php` | 5 | Eloquent + constraints |
| Esqueleto | `Unit/ExampleTest.php`, `Feature/ExampleTest.php` | 2 | exemplos padrão do Laravel |

**Não há testes unitários reais.** `tests/Unit` só contém o exemplo. As
Actions e Policies são testadas pela camada HTTP; a exceção é
`prevents deleting a profile that still has users`, que chama a Policy
diretamente.

## Padrões observados

- Autenticação nos testes HTTP via `actingAs()`. Isso **não** exercita o
  token Sanctum, por isso abilities e estado de token ficam fora da cobertura
  ([FIND-021](../findings/README.md#find-021--lacunas-de-testes-de-segurança)).
- Negação de acesso verificada por status (404 ou 403), não pelo corpo.
- Eventos: `Event::fake()` + `assertDispatched()` para o dispatch;
  requisição real + consulta em `audit_logs` para a reação.
- Constraints do banco verificadas esperando `QueryException`.

## Regra para mudanças futuras

Toda nova regra de autorização ou invariante de segurança deve entrar com um
teste que **falhe sem a regra**, e ser registrada em
[security-tests.md](security-tests.md).
