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
Fase 3.1 — Documentation & Architecture Reality Check   ← atual
    ↓
Fase 4 — Área Administrador (Blade)                     planejada
Fase 5 — Área Segurança (Testes)                        planejada
```

| Fase | Commit | Entrega confirmada | Evidência da numeração |
|---|---|---|---|
| 0 | `01da095` | PostgreSQL de teste, Pest, Sanctum, Larastan, strict mode | mensagem do commit ("fase 0") |
| 1 | `0dc300f` | Models, migrations, factories e seeders de usuários, perfis, menus, detalhes e auditoria; testes de model e constraints | **inferida por eliminação**; o commit não nomeia a fase |
| 2 | `7af2c02` | Login, logout, tokens, rate limit, Policies, `CheckPermission`, CRUD de usuários | `AuthorizationTest` (deste commit) diz que ProfileController é "escopo da Fase 3" |
| 3 | `0ef6ea8` | DTOs, Actions, eventos e listeners, CRUD de perfis e menus, atribuição de perfis, sync de permissões | [phase-03-iam-rest.md](phase-03-iam-rest.md) |
| 3.1 | — (não commitada) | Esta documentação em `docs/` | — |

Os detalhes históricos das Fases 0 a 2 além do que está nos commits e no
código **ainda não estão documentados**.

## Próximas fases

As Fases 4 e 5 foram citadas como próximos passos ao fim da Fase 3; não há
especificação delas no repositório. Findings que convém tratar **antes** da
Fase 4, porque a área administrativa depende deles: FIND-001, FIND-002,
FIND-004, FIND-005, FIND-007 e FIND-010. Veja [findings](../findings/README.md).
