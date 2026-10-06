# ADR-0009 — Sessão web e Bearer como adapters separados do mesmo domínio

[← ADRs](README.md) · [Autenticação](../../security/authentication.md) · [Autorização](../../security/authorization.md#área-administrativa-blade) · [Fase 4.2](../../phases/phase-04-2-user-management.md)

## Status

Accepted (Fase 4.2). Complementa o [ADR-0003](ADR-0003-sanctum.md) e o
[ADR-0008](ADR-0008-layered-authorization-model.md). Resolve a decisão
pendente do [FIND-022](../../findings/README.md#find-022--documentação-e-histórico-divergem-do-código).

## Context

- O commit `7af2c02` falava em "sanctum spa", mas `statefulApi()` nunca foi
  ativado. O FIND-022 pedia decidir se a autenticação SPA por cookie seria
  ativada ou abandonada.
- A Fase 4.1 criou a área administrativa em Blade, renderizada no servidor,
  com login por sessão (guard `web`). A Fase 4.2 colocou nela a primeira
  funcionalidade real, a administração de usuários.
- Havia duas formas de a interface chegar ao domínio: chamar a própria API
  por HTTP ou chamar Policies e Actions diretamente.

## Decision

1. **Dois adapters de entrada, um domínio.** A API REST (`routes/api.php`,
   `Api\V1`) autentica por **Bearer** (personal access token do Sanctum). A
   área administrativa (`routes/web.php`, `Web\Admin`) autentica por
   **sessão** (guard `web`, cookie, CSRF). Os dois chamam as mesmas Policies,
   Actions, eventos e invariantes. O Blade **não** chama a API por HTTP.
2. **Sem SPA por cookie.** `statefulApi()` continua desligado. O grupo `api`
   não inicia sessão, então um cookie de sessão nunca autentica a API.
   `RouteCoverageTest` impõe isso.
3. **Credenciais independentes.** O login web não emite token e o logout web
   não revoga tokens. Desativar a conta encerra as duas: `DeactivateUser`
   apaga os tokens e remove as sessões, e cada adapter ainda recusa o usuário
   inativo no próprio ponto de autenticação (callback do Sanctum e
   `EnsureUserIsActive`).
4. **Adapters traduzem, não decidem.** Cada adapter só traduz o resultado
   para o próprio protocolo: negação funcional como 404 JSON ou página 404;
   regra contextual como 403 nos dois; invariante como 409 JSON ou redirect
   com mensagem, a partir da mesma `LastActiveAdministratorException`.
5. **A sessão web é uma sessão administrativa.** Só abre sessão quem tem
   acesso à área administrativa (ao menos uma seção visível pelas Policies).
   Uma sessão que perde esse acesso é encerrada.

## Rationale

- Blade renderizado no servidor já tem sessão e CSRF nativos. A SPA por
  cookie existe para clientes JavaScript que chamam a API, e o NAPI não tem
  nenhum.
- Chamar a própria API por HTTP duplicaria a autenticação (a interface
  precisaria de um token), somaria latência e um ponto de falha, e não
  acrescentaria segurança: as regras já estão no domínio.
- Manter as credenciais separadas limita o raio de um vazamento: um token
  vazado não abre a interface, e um cookie roubado não chama a API.

## Alternatives Considered

- **Ativar `statefulApi()` e fazer a interface consumir a API.** Exigiria
  um frontend JavaScript e um CSRF da API, e misturaria os dois modos de
  autenticação nas mesmas rotas. Descartada.
- **Blade chamando a API com um token de serviço.** Teria um segundo
  principal (o serviço) além do usuário, e a auditoria teria de propagar o
  ator real. Descartada.
- **Remover as configurações SPA de `config/sanctum.php`.** `guard => ['web']`
  é o que permite `actingAs()` nos testes da API. Mantida, documentada como
  inerte em produção, porque nenhuma rota da API inicia sessão.

## Consequences

### Positive

- Uma regra nova (Policy, Action ou invariante) vale nos dois canais sem
  código extra. A Fase 4.2 demonstra isso com testes entre canais.
- Cada canal usa o mecanismo idiomático: Bearer na API, sessão e CSRF no
  Blade.

### Negative

- Duas superfícies de autenticação para manter: rate limit, mensagens e
  auditoria de login existem nas duas.
- A remoção ativa de sessões na desativação só funciona com o driver de
  sessão `database`. Com outro driver, a sessão termina no próximo request
  (`EnsureUserIsActive`).

## Security Impact

Mitiga o uso cruzado de credenciais (cookie na API, token na interface), a
divergência de regras entre canais e a escalação pela interface. Mantém
CSRF em toda mutação web.

## References

- `bootstrap/app.php` (sem `statefulApi()`; aliases `permission` e `admin.access`)
- `routes/api.php`, `routes/web.php`
- `app/Http/Middleware/{EnsureUserIsActive,EnsureUserCanAccessAdmin,CheckPermission}.php`
- `app/Http/Admin/AdminNavigation.php`
- `app/Domain/IAM/Actions/*`, `app/Domain/IAM/Exceptions/LastActiveAdministratorException.php`
- `tests/Feature/Security/RouteCoverageTest.php`, `tests/Feature/Admin/UserManagementTest.php`
