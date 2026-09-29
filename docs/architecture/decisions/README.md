# Architecture Decision Records

[← Documentação](../../README.md) · [Visão geral](../overview.md)

Um ADR registra uma decisão arquitetural, o contexto em que foi tomada, as
alternativas e as consequências. ADRs aceitos não são reescritos: se uma
decisão mudar, cria-se um novo ADR com status `Supersedes ADR-XXXX` e o antigo
passa a `Superseded by ADR-YYYY`.

## Sobre a motivação histórica

Os ADRs 0001 a 0004 foram escritos **retroativamente** na Fase 3.1. O
repositório mostra *o que* foi implementado, mas raramente registra *por que*.
Nesses casos o ADR diz explicitamente que não há evidência da motivação
original e descreve apenas as propriedades arquiteturais que a escolha
proporciona. Quando há evidência (comentário no código, mensagem de commit ou
decisão registrada na Fase 3), ela é citada.

## Índice

| ADR | Decisão | Status |
|---|---|---|
| [ADR-0001](ADR-0001-laravel.md) | Laravel 13, com `bootstrap/app.php` e sem `Kernel.php` | Accepted |
| [ADR-0002](ADR-0002-postgresql.md) | PostgreSQL com ULIDs, `citext`, `jsonb` e integridade referencial no banco | Accepted |
| [ADR-0003](ADR-0003-sanctum.md) | Sanctum com tokens opacos por usuário, expiração global de 7 dias e prefixo `napi_` | Accepted (complementado na Fase 3.2) |
| [ADR-0004](ADR-0004-policy-based-authorization.md) | Policies como camada de autorização por instância | Superseded by ADR-0008 |
| [ADR-0005](ADR-0005-event-driven-audit.md) | Auditoria de operações IAM como reação síncrona a eventos de domínio | Accepted (evoluído na Fase 3.2) |
| [ADR-0006](ADR-0006-route-permission-matrix.md) | Matriz perfil × menu como autorização por rota, negando com 404 | Superseded by ADR-0008 |
| [ADR-0007](ADR-0007-dto-action-boundary.md) | DTO + Action apenas para casos de uso com regra de negócio | Accepted |
| [ADR-0008](ADR-0008-layered-authorization-model.md) | Autorização em camadas: autenticação, ability do token, permissão funcional (`menus.key`), Policy e invariante de domínio | Accepted (Fase 3.2) |

## Template

```markdown
# ADR-000X — Título

## Status
Proposed | Accepted | Superseded by ADR-YYYY

## Context
## Decision
## Rationale
## Alternatives Considered
## Consequences
### Positive
### Negative
## Security Impact
## References
```
