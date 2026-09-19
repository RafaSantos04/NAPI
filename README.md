# NAPI

NAPI é um projeto de portfólio: uma "API Page" com duas grandes áreas — **IAM** (administração de usuários, perfis e menus) e **Segurança** (testes e ferramentas de segurança).

## Stack

- [Laravel 13](https://laravel.com)
- [PostgreSQL](https://www.postgresql.org/)
- [Laravel Sanctum](https://laravel.com/docs/sanctum) (autenticação via API tokens)
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

*Em construção.* Vai cobrir usuários (ULID), perfis, vínculo usuário-perfil, menus e vínculo menu-perfil, com trilha de auditoria (`audit_logs`).

### Segurança

*Em construção.* Área dedicada a testes e ferramentas de segurança da aplicação.
