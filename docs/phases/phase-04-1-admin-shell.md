# Fase 4.1 — Admin Shell (Blade)

[← Fases](README.md) · [Autenticação](../security/authentication.md#área-administrativa-sessão-web)

**Commits:** `a30deac` (sessão web), `5d759dd` (shell), `562b118` (testes), `ea89fad` (documentação)

## Objetivo

Abrir a Fase 4 (Área Administrativa) com a casca da interface: uma tela
preta em `/admin`, um botão **Entrar** no canto superior direito e login por
sessão. Sem dashboard, CRUDs ou menus administrativos.

## Decisão: sessão web, não a API

```text
Blade → routes/web.php → controllers Web\Admin → guard `web` (sessão)
```

O Blade **não** chama a API REST por HTTP nesta etapa. O login web não emite
token Sanctum e o logout web não revoga tokens: as duas formas de
autenticação são independentes. Ambas passam pelo mesmo
`users.is_active` e pela mesma auditoria (`UserLoggedIn`/`UserLoggedOut`).

## Rotas

| Método | URI | Nome | Middleware | Ação |
|---|---|---|---|---|
| GET | `/admin` | `admin.home` | `web` | `HomeController` — landing, aberta a visitantes |
| POST | `/admin/login` | `admin.login` | `web`, `guest` | `LoginController@store` |
| POST | `/admin/logout` | `admin.logout` | `web`, `auth` | `LoginController@destroy` |

Páginas internas futuras entram no grupo `auth` de `routes/web.php`.
`auth` devolve o visitante para `/admin` e `guest` devolve o usuário logado
para `/admin` (`bootstrap/app.php`).

## Entregue

| Área | Arquivos |
|---|---|
| Rotas | `routes/web.php` |
| Controllers | `app/Http/Controllers/Web/Admin/HomeController.php`, `.../Web/Admin/Auth/LoginController.php` |
| Validação, tentativa e rate limit | `app/Http/Requests/Web/Admin/Auth/LoginRequest.php` |
| Sessão de usuário desativado | `app/Http/Middleware/EnsureUserIsActive.php` (anexado ao grupo `web`) |
| Views | `resources/views/layouts/admin.blade.php`, `resources/views/admin/index.blade.php`, `resources/views/admin/auth/login-panel.blade.php` |
| Model | `User::getRememberTokenName()` retorna `''`: a tabela não tem `remember_token` e não há "lembrar-me" |
| Testes | `tests/Feature/Admin/AdminShellTest.php` (19 testes) |

## Interface

- Layout `layouts.admin` com `@yield('header')`, `@yield('content')` e
  `@stack('styles'|'scripts')`; espaço para sidebar, breadcrumbs e flash
  messages nas próximas subfases.
- CSS próprio, inline no layout: a tela funciona sem build do Vite.
- O painel de login é um `<details>` ancorado ao botão: abre e fecha por
  teclado sem JS. Um script curto foca o e-mail ao abrir e fecha com `Esc`
  ou clique fora. Após falha, o painel volta aberto com o erro.
- Logado: e-mail do usuário e botão **Sair**.

## Segurança

| Controle | Implementação |
|---|---|
| CSRF | `@csrf` no login e no logout; middleware padrão do grupo `web` |
| Mensagem uniforme | e-mail inexistente, senha errada, conta inativa e conta excluída respondem `Credenciais inválidas.` |
| Regeneração de sessão | `session()->regenerate()` após login |
| Logout | `logout()` + `session()->invalidate()` + `regenerateToken()` |
| Rate limit | 5 tentativas por `email + IP` (`RateLimiter`, padrão do Breeze) |
| Usuário desativado com sessão aberta | `EnsureUserIsActive` encerra a sessão no próximo request |

## Fora do escopo / próximos passos

- ~~Restringir a área administrativa por permissão funcional~~: feito na
  [Fase 4.2](phase-04-2-user-management.md#acesso-à-área-administrativa).
- Dashboard, navegação pela árvore de menus e páginas internas.
- ~~Decidir se as telas consumirão Actions do domínio diretamente ou a API~~:
  Actions diretamente ([ADR-0009](../architecture/decisions/ADR-0009-web-session-and-bearer-adapters.md)).
- Auditar tentativas de login falhas (mesma pendência da API).
