# NAPI

NAPI é um projeto de portfólio: uma "API Page" com duas grandes áreas — **IAM** (administração de usuários, perfis e menus) e **Segurança** (testes e ferramentas de segurança).

## Stack

- [Laravel 13](https://laravel.com)
- [PostgreSQL](https://www.postgresql.org/)
- [Laravel Sanctum](https://laravel.com/docs/sanctum) (autenticação via API tokens)
- Blade + sessão web (área administrativa)
- [Pest](https://pestphp.com/) (testes)
- [Larastan](https://github.com/larastan/larastan) + [Laravel Pint](https://laravel.com/docs/pint) (análise estática e estilo de código)

## Como rodar

1. Clone o repositório e instale as dependências:

    ```bash
    composer install
    ```

2. Copie o `.env.example` para `.env` e preencha as credenciais do PostgreSQL local:

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3. Crie os bancos `napi` (desenvolvimento) e `napi_test` (testes) no PostgreSQL, então rode as migrations:

    ```bash
    php artisan migrate
    php artisan migrate --env=testing
    ```

4. Suba a aplicação:

    ```bash
    php artisan serve
    ```

## Testes e qualidade

```bash
composer test      # Pest
composer lint       # Laravel Pint (--test)
composer analyse    # PHPStan / Larastan
```

## Áreas do projeto

### IAM

API REST v1 com usuários (ULID), perfis, menus, matriz de permissões perfil × menu, tokens Sanctum e trilha de auditoria (`audit_logs`). A autorização é feita em camadas (autenticação, ability do token, permissão funcional, Policy e invariante de domínio), descritas no [ADR-0008](docs/architecture/decisions/ADR-0008-layered-authorization-model.md). Veja [`docs/modules/iam.md`](docs/modules/iam.md).

### Área administrativa

*Em construção.* Interface Blade em `/admin` com login por sessão web, independente dos tokens da API ([Fase 4.1](docs/phases/phase-04-1-admin-shell.md)). Administração de usuários em `/admin/users`, com as mesmas Policies, Actions e auditoria da API; entram só usuários com permissão funcional sobre alguma área disponível ([Fase 4.2](docs/phases/phase-04-2-user-management.md)).

### Segurança

*Em construção (Fase 5).* **Security Lab** em `/admin/security`: testes controlados que executam a versão vulnerável e a versão protegida de uma operação, lado a lado, só sobre dados sintéticos. Há dois: [IDOR / BOLA](docs/security/idor.md) e [Mass Assignment](docs/security/mass-assignment.md). Desligado por padrão; ligue com `SECURITY_LAB_ENABLED=true` e rode `php artisan migrate` e `php artisan db:seed --class=SecurityLabSeeder`. Veja [`docs/modules/security.md`](docs/modules/security.md).

## Documentação

A documentação arquitetural completa do projeto (arquitetura, ADRs, segurança, threat model, API, banco, testes e findings) está em [`docs/`](docs/README.md).
