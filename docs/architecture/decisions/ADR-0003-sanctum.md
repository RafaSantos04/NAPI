# ADR-0003 — Sanctum com tokens opacos, expiração de 7 dias e prefixo `napi_`

[← ADRs](README.md) · [Autenticação](../../security/authentication.md)

## Status

Accepted (registrado retroativamente na Fase 3.1)

## Context

A API precisa autenticar clientes de forma stateless e permitir que um
usuário gerencie e revogue as próprias credenciais.

## Decision

- **Laravel Sanctum 4** com *personal access tokens*: tokens opacos
  aleatórios, armazenados como hash SHA-256 em `personal_access_tokens` e
  enviados como `Authorization: Bearer`.
- Expiração global de **7 dias** (`config/sanctum.php`, `expiration`), além
  de `expires_at` por token.
- Prefixo **`napi_`** nos tokens (`token_prefix`).
- Endpoints para o próprio usuário listar, criar e revogar tokens
  (`TokenController`).
- Logout revoga **todos** os tokens do usuário.

## Rationale

Não há evidência da motivação histórica para Sanctum. O commit `7af2c02` diz
"sanctum spa + tokens", mas a parte SPA (autenticação por cookie) **não está
ativa**: `bootstrap/app.php` não chama `statefulApi()`.

Há evidência de intenção para o prefixo: o `config/sanctum.php` associa o
prefixo a programas de *secret scanning*, e o projeto trocou o valor padrão
vazio por `napi_`. Isso torna tokens vazados em repositórios detectáveis por
ferramentas de varredura.

Propriedades que o desenho proporciona:

- Tokens opacos com hash no banco: um vazamento do banco não entrega tokens
  utilizáveis.
- Revogação imediata é uma exclusão de linha, sem a complexidade de
  revogar JWTs.
- A expiração global limita a janela de uso de um token vazado.

## Alternatives Considered

Não documentadas. Alternativas razoáveis: JWT (stateless, mas revogação
difícil), Passport/OAuth2 (excessivo sem clientes de terceiros) e sessão por
cookie (exige CSRF e estado).

## Consequences

### Positive

- Revogação e listagem de tokens triviais.
- Cada requisição autenticada é verificada contra o banco.

### Negative

- **Abilities são emitidas mas não aplicadas.** `read`/`write`/`delete` são
  gravadas no token, mas nenhuma rota as verifica
  ([FIND-001](../../findings/README.md#find-001--abilities-de-token-não-são-aplicadas)).
- A expiração global de 7 dias limita silenciosamente `expires_in_days` (até
  365): o `expires_at` exibido na listagem pode ser posterior à real
  invalidação ([FIND-015](../../findings/README.md#find-015--expiração-de-token-aceita-valores-que-nunca-terão-efeito)).
- Tokens expirados não são removidos (`sanctum:prune-expired` não está
  agendado).
- Desativar um usuário (`is_active = false`) não invalida seus tokens
  ([FIND-003](../../findings/README.md#find-003--usuário-desativado-continua-autenticado)).

## Security Impact

Mitiga replay de token por tempo indeterminado e vazamento de tokens em
repositórios. Não mitiga escopo excessivo de token (abilities inertes) nem
sessões de usuários desativados.

## References

- `config/sanctum.php`
- `app/Http/Controllers/Api/V1/Auth/AuthController.php`, `TokenController.php`
- `database/migrations/2026_09_19_210936_create_personal_access_tokens_table.php`
- `tests/Feature/IAM/AuthenticationTest.php`, `TokenManagementTest.php`
