# Fases do projeto

[← Documentação](../README.md)

Reconstruído a partir do histórico git, de comentários no código e do
contexto da Fase 3. Onde não há evidência, isso é dito.

```text
Fase 0 — Setup
    ↓
Fase 1 — Modelagem IAM         (numeração inferida)
    ↓
Fase 2 — Autenticação e autorização
    ↓
Fase 3 — IAM REST
    ↓
Fase 3.1 — Documentation & Architecture Reality Check
    ↓
Fase 3.2 — IAM Security Hardening & Authorization Consolidation
    ↓
Fase 4 — Área Administrador (Blade)
    └ 4.1 — Admin Shell (sessão web)                    ← atual
Fase 5 — Área Segurança (Testes)                        planejada
```

| Fase | Commit | Entrega confirmada | Evidência da numeração |
|---|---|---|---|
| 0 | `01da095` | PostgreSQL de teste, Pest, Sanctum, Larastan, strict mode | mensagem do commit ("fase 0") |
| 1 | `0dc300f` | Models, migrations, factories e seeders de usuários, perfis, menus, detalhes e auditoria; testes de model e constraints | **inferida por eliminação**; o commit não nomeia a fase |
| 2 | `7af2c02` | Login, logout, tokens, rate limit, Policies, `CheckPermission`, CRUD de usuários | `AuthorizationTest` (deste commit) diz que ProfileController é "escopo da Fase 3" |
| 3 | `0ef6ea8` | DTOs, Actions, eventos e listeners, CRUD de perfis e menus, atribuição de perfis, sync de permissões | [phase-03-iam-rest.md](phase-03-iam-rest.md) |
| 3.1 | `634f194`…`ead930f` | Esta documentação em `docs/`, ADRs 0001–0007, threat model e 22 findings | mensagens dos commits |
| 3.2 | pending | Correção dos findings prioritários, autorização em camadas ([ADR-0008](../architecture/decisions/ADR-0008-layered-authorization-model.md)), suíte `tests/Feature/Security` | [phase-03-2-iam-hardening.md](phase-03-2-iam-hardening.md) |
| 4.1 | pending | Shell Blade em `/admin`, login por sessão web (guard `web`), layout base | [phase-04-1-admin-shell.md](phase-04-1-admin-shell.md) |

Os detalhes históricos das Fases 0 a 2 além do que está nos commits e no
código **ainda não estão documentados**.

## Próximas fases

A Fase 4 começou pela [4.1](phase-04-1-admin-shell.md) (shell Blade e login
por sessão). A Fase 5 foi citada como próximo passo ao fim da Fase 3; não há
especificação dela no repositório. Os findings que bloqueavam a Fase 4
(FIND-001, FIND-002, FIND-004, FIND-005, FIND-007 e FIND-010) foram
resolvidos na Fase 3.2. A Fase 4 herda duas pendências diretas: o endpoint
de desativação de usuário, que deve usar a invariante de administrador, e o
FIND-019. Veja [findings](../findings/README.md).
