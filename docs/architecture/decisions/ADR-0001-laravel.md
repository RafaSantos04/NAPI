# ADR-0001 — Laravel 13 como framework da aplicação

[← ADRs](README.md)

## Status

Accepted (registrado retroativamente na Fase 3.1)

## Context

O NAPI precisa de uma API REST com autenticação, autorização, validação,
ORM, eventos e testes, mantida por uma pessoa, como peça de portfólio.

## Decision

A aplicação usa **Laravel 13** (`laravel/framework ^13.17`, instalado 13.32)
sobre PHP `^8.3`. A configuração segue o formato moderno do framework:
`bootstrap/app.php` com `Application::configure()`, sem `app/Http/Kernel.php`
e sem `EventServiceProvider` próprio. O único provider da aplicação é
`AppServiceProvider`.

## Rationale

Não há evidência no repositório da motivação histórica para escolher Laravel.
O README apenas lista a stack.

Arquiteturalmente, a escolha fornece, prontos e integrados, os blocos que o
IAM usa: FormRequests, Policies/Gate, Eloquent, Events, API Resources,
rate limiting e Sanctum. Assim, o esforço do projeto vai para as regras de
IAM, e não para infraestrutura. O formato `bootstrap/app.php` concentra
roteamento, aliases de middleware e tratamento de exceções num único arquivo
declarativo.

## Alternatives Considered

Não documentadas historicamente. Alternativas razoáveis seriam Symfony, que
tem mais configuração explícita, ou um micro-framework como Slim, que exigiria
montar autorização e ORM à mão.

## Consequences

### Positive

- Convenções fortes: Policies descobertas por nome, route model binding,
  Resources.
- `Model::shouldBeStrict()` detecta lazy loading e mass assignment silencioso
  nos testes.

### Negative

- **Auto-discovery de listeners é implícita.** O Laravel 13 descobre
  listeners em `app/Listeners` por type-hint. Registrá-los também manualmente
  duplica a execução, o que aconteceu na Fase 3
  ([FIND-002](../../findings/README.md#find-002--listeners-de-auditoria-registrados-em-duplicidade)).
- Helpers globais (`request()`, `auth()`) tornam fácil acoplar código de
  domínio ao contexto HTTP sem perceber.

## Security Impact

- Proteção contra mass assignment via `#[Fillable]` e `validated()`.
- Hash de senha pelo cast `hashed` em `User`.
- Em ambiente local, exceções não tratadas expõem stack trace porque
  `.env.example` traz `APP_DEBUG=true`.

## References

- `composer.json`, `bootstrap/app.php`, `bootstrap/providers.php`
- `app/Providers/AppServiceProvider.php`
- Commit `01da095` (fase 0: strict mode, Pest, Sanctum, Larastan)
