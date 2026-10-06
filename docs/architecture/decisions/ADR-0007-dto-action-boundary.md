# ADR-0007 — DTO + Action apenas para casos de uso com regra de negócio

[← ADRs](README.md) · [Ciclo de vida](../request-lifecycle.md)

## Status

Accepted (Fase 3). Complementado pela Fase 4.2; veja
[Evolução na Fase 4.2](#evolução-na-fase-42).

## Context

Parte das operações do IAM é CRUD sem regra além da autorização e da
validação. Outras carregam invariantes (não remover o último admin) ou
efeitos colaterais (auditoria de mudança de privilégio).

## Decision

- Apenas dois casos de uso usam **DTO + Action**:
  - `AssignProfilesToUserDto` → `AssignProfilesToUser`
  - `SyncMenuPermissionsDto` → `SyncMenuPermissions`
- O restante do CRUD chama o model diretamente no controller com
  `$request->validated()`.
- DTOs são `readonly` e construídos a partir do FormRequest com `from()`.
- Actions expõem um método estático `execute()` e disparam o evento de
  domínio.

## Rationale

A Fase 3 limitou deliberadamente os DTOs a dois ("não adicione mais").

- O **FormRequest** pertence à camada HTTP: autoriza e valida.
- O **DTO** é o dado já validado atravessando a fronteira. A Action recebe
  `AssignProfilesToUserDto`, não um `Request`, então pode ser chamada por um
  comando Artisan, um job ou um teste sem simular HTTP.
- A **Action** é o lugar da regra de negócio e do evento. Ela protege a
  invariante mesmo que outro ponto de entrada, como a futura área Blade, a
  chame.

Aplicar o padrão ao CRUD simples adicionaria duas classes por operação sem
proteger nenhuma regra.

## Alternatives Considered

- DTO/Action para todo endpoint: uniforme, mas cerimonial.
- Regra de negócio no controller: seria perdida por qualquer outro ponto de
  entrada.
- Actions como classes injetáveis (`__invoke` via container): permitiriam
  injetar dependências, como um provedor de contexto de requisição. O
  estático foi o formato especificado na Fase 3.

## Consequences

### Positive

- Invariantes críticas ficam num único lugar reutilizável.
- As Actions são testáveis sem HTTP, embora ainda não existam testes
  unitários delas.

### Negative

- O desacoplamento é incompleto: as Actions leem `request()->ip()`, então
  chamá-las fora de uma requisição grava IP nulo ou incorreto.
- Métodos estáticos impedem injeção de dependências e dublês no container.
- A regra do último admin lança `\Exception` genérica, que vira HTTP 500
  ([FIND-006](../../findings/README.md#find-006--regra-do-último-administrador-tem-janela-de-corrida-e-resposta-500)).
- Nenhuma Action usa transação.

## Security Impact

Centraliza a proteção contra lockout administrativo e garante que toda
mudança de perfis ou permissões pela Action gere evento de auditoria.

## Evolução na Fase 4.2

O critério não mudou: DTO + Action quando há regra de negócio ou efeito que
precisa valer em todo ponto de entrada. Com a área administrativa (segundo
adapter, [ADR-0009](ADR-0009-web-session-and-bearer-adapters.md)), mais quatro
casos de uso passaram a se enquadrar:

| Caso de uso | Por que |
|---|---|
| `CreateUserDto` → `CreateUser` | API e admin criariam a conta e a auditoria em dois lugares; o DTO fixa os únicos campos aceitos (sem `is_active`, perfis ou flags) |
| `UpdateUserDto` → `UpdateUser` | mesma duplicação, mais o cálculo dos campos alterados para a auditoria |
| `DeactivateUser` | invariante do último admin, revogação de tokens e remoção de sessões |
| `ActivateUser` | operação explícita e auditada, que não restaura credenciais antigas |

Status não tem DTO: a entrada é só o usuário da rota. O restante do CRUD de
perfis e menus continua chamando o model no controller. As consequências
negativas sobre `request()->ip()`, transações e `\Exception` genérica foram
resolvidas na Fase 3.2 (`AuditContext`, `DB::transaction`,
`LastActiveAdministratorException`).

## References

- `app/DTOs/*`, `app/Domain/IAM/Actions/*`
- `app/Http/Controllers/Api/V1/UserController.php`, `UserStatusController.php`, `app/Http/Controllers/Web/Admin/*` (Fase 4.2)
- `app/Http/Controllers/Api/V1/UserProfileController.php`, `ProfileController::syncMenus`
- `tests/Feature/IAM/UserProfileSyncTest.php`
