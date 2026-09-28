# ADR-0004 — Policies como camada de autorização por instância

[← ADRs](README.md) · [Autorização](../../security/authorization.md) · [ADR-0006](ADR-0006-route-permission-matrix.md)

## Status

Accepted (registrado retroativamente na Fase 3.1; o método `assignProfiles`
foi decidido na Fase 3)

## Context

Algumas regras de acesso dependem da instância e não apenas da rota:

- perfis de sistema não podem ser alterados;
- um perfil com usuários não pode ser excluído;
- um menu com filhos não pode ser excluído;
- um admin não pode excluir a si mesmo;
- um usuário pode ver a si mesmo, mas não aos outros.

## Decision

Cada model de IAM tem uma Policy registrada explicitamente com
`Gate::policy()` no `AppServiceProvider`:

- `UserPolicy`: `viewAny`, `view`, `create`, `update`, `delete`, `assignProfiles`
- `ProfilePolicy`: `viewAny`, `view`, `create`, `update`, `delete`, `syncMenus`
- `MenuPolicy`: `viewAny`, `view`, `create`, `update`, `delete`

FormRequests delegam à Policy em `authorize()` sem duplicar a regra.
Controllers chamam a Policy em `index`, `show` e `destroy`.

As Policies decidem pelo **slug do perfil** do ator (`hasProfile('admin')`,
`hasProfile('dev')`), não pela matriz `menu_profiles`.

## Rationale

Policies concentram a regra de autorização junto ao model que ela protege.
Controllers, middleware e uma futura interface não precisam reimplementar a
decisão; perguntam `can('update', $profile)`. Delegar em
`FormRequest::authorize()` faz a checagem acontecer antes da validação, e o
controller recebe um pedido já autorizado.

**Decisão da Fase 3 sobre `assignProfiles`.** A primeira versão proposta
bloqueava incondicionalmente a auto-atribuição (`$user->id === $target->id`).
Isso tornava inalcançável a regra "não remover o último admin", que vive na
Action. Com concordância do responsável pelo projeto, a Policy passou a
responder só "o ator é admin?", e a invariante de lockout ficou na Action.
Assim a Policy decide *quem pode* e a Action protege *o estado que não pode
ser violado*.

## Alternatives Considered

- **Checagem inline nos controllers**: duplicaria regras entre endpoints e
  uma futura interface Blade.
- **Pacote de RBAC (ex.: spatie/laravel-permission)**: não adotado. Não há
  registro do motivo; o projeto modelou a própria matriz
  ([ADR-0006](ADR-0006-route-permission-matrix.md)).
- **Policies lendo a matriz `menu_profiles`**: seria a alternativa que
  unifica as duas fontes de autorização. Não foi feita
  ([FIND-004](../../findings/README.md#find-004--duas-fontes-de-autorização-que-não-se-conhecem)).

## Consequences

### Positive

- Regras de instância testáveis diretamente (`$admin->can('delete', $profile)`
  em `AuthorizationTest`).
- Perfis de sistema ficam protegidos contra edição, exclusão e alteração de
  permissões pela API.

### Negative

- **Duas fontes de verdade.** A matriz é dados; as Policies são código com
  slugs fixos. Um perfil customizado recebe permissões na matriz, mas as
  Policies continuam exigindo `admin` ou `dev`, então o perfil customizado
  quase nunca é efetivo.
- A resposta em negação varia: 404 quando o controller chama a Policy à mão,
  403 quando a chamada vem do FormRequest ou de `$this->authorize()`.
- `UserPolicy::viewAny` permite `dev`, mas `view` não: `dev` lista todos os
  usuários, mas não consegue ver um usuário específico
  ([FIND-009](../../findings/README.md#find-009--perfil-dev-lista-todos-os-usuários-mas-não-pode-ver-nenhum)).

## Security Impact

Mitiga escalação de privilégio (só admin atribui perfis ou altera
permissões), adulteração de perfis de sistema e exclusão de estruturas com
dependentes. Veja as invariantes em
[security-policies.md](../../security/security-policies.md).

## References

- `app/Policies/*`, `app/Providers/AppServiceProvider.php`
- `app/Http/Requests/*Request.php`
- `tests/Feature/IAM/AuthorizationTest.php`, `ProfileTest.php`, `MenuTest.php`, `UserProfileSyncTest.php`
