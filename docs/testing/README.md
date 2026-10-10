# Estratégia de testes

[← Documentação](../README.md) · [Testes de segurança](security-tests.md)

## Configuração

- **Pest 4** sobre PHPUnit 12 (`composer test`).
- **Banco real**: PostgreSQL `napi_test` (`phpunit.xml`), não SQLite, para
  exercitar `citext`, FKs e cascades de verdade
  ([ADR-0002](../architecture/decisions/ADR-0002-postgresql.md)).
- `RefreshDatabase` em todo `tests/Feature` (`tests/Pest.php`).
- `tests/Feature/IAM/*`, `tests/Feature/Security/*`, `tests/Feature/Admin/*` (desde a Fase 4.2) e `tests/Feature/SecurityLab/*` (Fase 5.1, com o laboratório ligado por `config()`) rodam o `DatabaseSeeder`
  antes de cada teste. O escopo é limitado a esses diretórios para não colidir
  com testes que criam o próprio perfil `admin` via factory (comentário em
  `tests/Pest.php`).
- `Model::shouldBeStrict()` fica ativo nos testes: lazy loading e mass
  assignment indevidos quebram a suíte.
- Qualidade complementar: `composer lint` (Pint) e `composer analyse`
  (Larastan nível 6 em `app/`).

## Inventário (264 testes, Fase 5.1)

| Categoria | Arquivo | Testes | Nível |
|---|---|---|---|
| Authentication | `Feature/IAM/AuthenticationTest.php` | 6 | HTTP |
| Authorization | `Feature/IAM/AuthorizationTest.php` | 6 | HTTP + Policy direta |
| Profiles | `Feature/IAM/ProfileTest.php` | 6 | HTTP |
| Menus | `Feature/IAM/MenuTest.php` | 4 | HTTP |
| Users ↔ Profiles | `Feature/IAM/UserProfileSyncTest.php` | 4 | HTTP + evento |
| Tokens | `Feature/IAM/TokenManagementTest.php` | 3 | HTTP |
| Audit | `Feature/IAM/AuditEventTest.php` | 2 | HTTP + banco |
| Security: abilities | `Feature/Security/TokenAbilityTest.php` | 14 | HTTP, Bearer real |
| Security: usuário inativo | `Feature/Security/DisabledUserAuthenticationTest.php` | 6 | HTTP, Bearer real |
| Security: permissões funcionais | `Feature/Security/AuthorizationMatrixTest.php` | 23 | model + HTTP, Bearer real |
| Security: escalação | `Feature/Security/PrivilegeEscalationTest.php` | 15 | HTTP, Bearer real |
| Security: último admin | `Feature/Security/AdminInvariantTest.php` | 7 | HTTP + Action |
| Security: auditoria | `Feature/Security/AuditIntegrityTest.php` | 8 | HTTP + banco + evento |
| Security: árvore de menus | `Feature/Security/MenuTreeTest.php` | 7 | HTTP, Bearer real |
| Security: cobertura de rotas | `Feature/Security/RouteCoverageTest.php` | 11 | rotas registradas + HTTP |
| Security Lab: acesso e feature flag | `Feature/SecurityLab/SecurityLabAccessTest.php` | 22 | HTTP, sessão `web` + Action + seeder |
| Security Lab: teste IDOR | `Feature/SecurityLab/IdorTestExecutionTest.php` | 23 | HTTP + Action + Policy + constraints |
| Security Lab: histórico | `Feature/SecurityLab/SecurityLabHistoryTest.php` | 7 | HTTP |
| Admin: shell e sessão | `Feature/Admin/AdminShellTest.php` | 19 | HTTP, sessão `web` |
| Admin: usuários | `Feature/Admin/UserManagementTest.php` | 49 | HTTP (sessão e Bearer) + Action |
| Model/DB: Users | `Feature/UserTest.php` | 10 | Eloquent + constraints |
| Model/DB: Profiles | `Feature/ProfileTest.php` | 5 | Eloquent + constraints |
| Model/DB: Menus | `Feature/MenuTest.php` | 5 | Eloquent + constraints |
| Esqueleto | `Unit/ExampleTest.php`, `Feature/ExampleTest.php` | 2 | exemplos padrão do Laravel |

**Não há testes unitários reais.** `tests/Unit` só contém o exemplo. As
Actions e Policies são testadas pela camada HTTP; a exceção é
`prevents deleting a profile that still has users`, que chama a Policy
diretamente.

## Padrões observados

- `tests/Feature/IAM/*` autentica com `actingAs()`, que **não** exercita o
  token Sanctum. Desde a Fase 3.2, `tests/Feature/Security/*` usa tokens reais
  (`asToken()`/`tokenFor()` em `tests/Pest.php`), cobrindo abilities e estado
  do token ([FIND-021](../findings/README.md#find-021--lacunas-de-testes-de-segurança)).
- `tests/Feature/Admin/*` autentica pela sessão. `adminLogin()` passa pelo
  formulário real (a sessão guarda o login); `actingAs()` só serve quando a
  persistência da sessão não importa. `adminUser()` cria um usuário com o
  perfil `admin`.
- Auditoria verificada por **cardinalidade** (`count() === 1`), não só por
  `exists()`.
- Negação de acesso verificada por status (404 ou 403), não pelo corpo.
- Eventos: `Event::fake()` + `assertDispatched()` para o dispatch;
  requisição real + consulta em `audit_logs` para a reação.
- Constraints do banco verificadas esperando `QueryException`.

## Regra para mudanças futuras

Toda nova regra de autorização ou invariante de segurança deve entrar com um
teste que **falhe sem a regra**, e ser registrada em
[security-tests.md](security-tests.md).
