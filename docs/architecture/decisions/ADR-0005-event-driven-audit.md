# ADR-0005 — Auditoria de operações IAM via eventos de domínio síncronos

[← ADRs](README.md) · [Auditoria](../../security/audit.md)

## Status

Accepted (Fase 3)

## Context

Operações sensíveis de IAM, como mudar perfis de alguém ou alterar a matriz
de permissões de um perfil, precisam deixar trilha. Até a Fase 2 toda
auditoria era um `AuditLog::create()` inline no controller.

## Decision

- Actions de domínio disparam eventos (`ProfileAssigned`,
  `PermissionChanged`) depois de alterar o estado.
- O login dispara `UserLoggedIn`.
- Listeners dedicados (`AuditProfileAssignment`, `AuditPermissionChange`,
  `AuditUserLogin`) gravam em `audit_logs`.
- Eventos e listeners são **síncronos**: nenhum implementa `ShouldQueue`.
- O IP de origem é capturado na Action e **carregado no evento**, para que o
  listener não precise ler o `Request`.

## Rationale

A regra da Fase 3 foi: "Events/Listeners não conhecem HTTP; o Listener grava
em `audit_logs`". O motivo arquitetural é separar o **fato** ("perfis foram
atribuídos") da **reação** ("registrar na auditoria"):

- a Action não depende da infraestrutura de auditoria;
- novas reações ao mesmo fato (notificação, invalidação de cache, métrica)
  entram como novos listeners, sem tocar a Action;
- o evento é um contrato explícito do que aconteceu.

A escolha por eventos síncronos garante que a linha de auditoria exista quando
a resposta HTTP é enviada. Isso é necessário para os testes que verificam
`audit_logs` logo após a requisição, e evita perder auditoria se a fila não
estiver rodando.

## Alternatives Considered

- **Auditoria inline** (padrão anterior, ainda usado em logout e exclusão de
  usuário): acopla controller e auditoria.
- **Model observers**: reagiriam a qualquer `save()`, mas não carregam o
  significado da operação. "Três linhas de pivot mudaram" não é o mesmo que
  "perfis atribuídos".
- **Listeners enfileirados**: desacoplam o tempo de resposta, mas podem
  perder o contexto do ator (`auth()` não existe no worker) e a auditoria
  deixa de ser imediata.

## Consequences

### Positive

- As Actions `AssignProfilesToUser` e `SyncMenuPermissions` não conhecem
  `AuditLog`.
- Adicionar um consumidor é registrar um listener.

### Negative

Limitações reais encontradas no Reality Check:

- **Listeners registrados em duplicidade**: auto-discovery e `Event::listen`
  no `AppServiceProvider`. Cada evento grava duas linhas
  ([FIND-002](../../findings/README.md#find-002--listeners-de-auditoria-registrados-em-duplicidade)).
- **O ator não viaja no evento.** `AuditProfileAssignment` e
  `AuditPermissionChange` usam `auth()->id()`, portanto dependem do contexto
  de autenticação do processo. Enfileirar esses listeners gravaria
  `user_id = null`.
- **A Action ainda lê `request()->ip()`.** O desacoplamento HTTP chega ao
  listener, mas não à Action.
- **Sem atomicidade.** Estado e auditoria não estão na mesma transação. Se o
  listener falhar, o estado já mudou e a resposta é 500.
- **Cobertura parcial.** CRUD de usuários, perfis e menus e operações de
  token não disparam eventos, portanto não são auditados
  ([FIND-012](../../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria)).

## Security Impact

Dá rastreabilidade às duas operações que mais alteram privilégios: atribuir
perfis e mudar permissões. A trilha ainda é mutável e parcial; veja
[audit.md](../../security/audit.md).

## References

- `app/Domain/IAM/Actions/*`, `app/Events/*`, `app/Listeners/*`
- `app/Providers/AppServiceProvider.php` (registro manual)
- `tests/Feature/IAM/AuditEventTest.php`, `UserProfileSyncTest.php`
