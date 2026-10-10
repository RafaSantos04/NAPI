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
    ├ 4.1 — Admin Shell (sessão web)
    └ 4.2 — Administração de Usuários
    ↓
Fase 5 — Área Segurança
    ├ 5.1 — Security Lab Foundation + IDOR/BOLA
    └ 5.2 — Security Lab: Mass Assignment               ← atual
```

| Fase | Commit | Entrega confirmada | Evidência da numeração |
|---|---|---|---|
| 0 | `01da095` | PostgreSQL de teste, Pest, Sanctum, Larastan, strict mode | mensagem do commit ("fase 0") |
| 1 | `0dc300f` | Models, migrations, factories e seeders de usuários, perfis, menus, detalhes e auditoria; testes de model e constraints | **inferida por eliminação**; o commit não nomeia a fase |
| 2 | `7af2c02` | Login, logout, tokens, rate limit, Policies, `CheckPermission`, CRUD de usuários | `AuthorizationTest` (deste commit) diz que ProfileController é "escopo da Fase 3" |
| 3 | `0ef6ea8` | DTOs, Actions, eventos e listeners, CRUD de perfis e menus, atribuição de perfis, sync de permissões | [phase-03-iam-rest.md](phase-03-iam-rest.md) |
| 3.1 | `634f194`…`ead930f` | Esta documentação em `docs/`, ADRs 0001–0007, threat model e 22 findings | mensagens dos commits |
| 3.2 | `2392411`…`dc5066a` | Correção dos findings prioritários, autorização em camadas ([ADR-0008](../architecture/decisions/ADR-0008-layered-authorization-model.md)), suíte `tests/Feature/Security` | [phase-03-2-iam-hardening.md](phase-03-2-iam-hardening.md) |
| 4.1 | `a30deac`…`ea89fad` | Shell Blade em `/admin`, login por sessão web (guard `web`), layout base | [phase-04-1-admin-shell.md](phase-04-1-admin-shell.md) |
| 4.2 | `c797865`…`439b18f` | Administração de usuários (listagem, detalhe, criação, edição, ativação/desativação, perfis), acesso ao admin por permissão, Actions compartilhadas com a API, Laravel Boost | [phase-04-2-user-management.md](phase-04-2-user-management.md) |
| 5.1 | `6d8ee6e`…`585b3d4` | Domínio Security: laboratório controlado com feature flag, recursos e personas sintéticos, teste IDOR/BOLA (vulnerável × protegido), `security_test_runs`, [ADR-0010](../architecture/decisions/ADR-0010-controlled-security-lab.md) | [phase-05-1-security-lab-idor.md](phase-05-1-security-lab-idor.md) |
| 5.2 | pending | Segundo teste do laboratório: Mass Assignment / autorização em nível de propriedade sobre o mesmo documento sintético (`is_approved`), com a escrita desfeita depois de observada; comparação IDOR × Mass Assignment (Abstraction Review) | [phase-05-2-security-lab-mass-assignment.md](phase-05-2-security-lab-mass-assignment.md) |

Os detalhes históricos das Fases 0 a 2 além do que está nos commits e no
código **ainda não estão documentados**.

## Próximas fases

A Fase 4 começou pela [4.1](phase-04-1-admin-shell.md) (shell Blade e login
por sessão) e seguiu com a [4.2](phase-04-2-user-management.md)
(administração de usuários, que entregou o endpoint de desativação herdado
da 3.2). As faixas de commit da 3.2, da 4.1, da 4.2 e da 5.1 vêm do `git log`. A Fase 5 começou
pela [5.1](phase-05-1-security-lab-idor.md), que criou o Security Lab e o
primeiro teste (IDOR/BOLA), e seguiu com a
[5.2](phase-05-2-security-lab-mass-assignment.md), o teste de Mass
Assignment. Com dois casos concretos, a 5.2 registrou o que se repetiu entre
eles e o que merece abstração, para orientar a 5.3. Os próximos testes
candidatos (escalação de privilégio, abilities de token, rate limiting,
CSRF) não estão implementados. Os findings que bloqueavam a Fase 4
(FIND-001, FIND-002, FIND-004, FIND-005, FIND-007 e FIND-010) foram
resolvidos na Fase 3.2. Continuam pendentes para a Fase 4 o FIND-019
(menus) e, antes de qualquer tela com CPF, o FIND-008. Veja [findings](../findings/README.md).
