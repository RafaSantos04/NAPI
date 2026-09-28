# Auditoria

[← Segurança](README.md) · [ADR-0005](../architecture/decisions/ADR-0005-event-driven-audit.md) · [Schema](../database/schema.md#audit_logs)

## Tabela `audit_logs`

| Coluna | Tipo | Conteúdo |
|---|---|---|
| `id` | ULID PK | — |
| `user_id` | ULID, FK `users` `set null` | **ator** da ação |
| `action` | `varchar(50)` | identificador da ação |
| `subject_type` / `subject_id` | `varchar(100)` / ULID | entidade afetada (índice composto) |
| `ip` | `inet` (`ipAddress`) | IP de origem |
| `user_agent` | `text` | User-Agent |
| `meta` | `jsonb` | dados específicos da ação |
| `created_at` | `timestamp`, `useCurrent()` | preenchido pelo **banco**; não há `updated_at` (`$timestamps = false`) |

Índices: `(user_id, created_at)` para a trilha de um ator e
`(subject_type, subject_id)` para o histórico de uma entidade.

## Ações auditadas

| `action` | Origem | Ator | Subject | IP | UA | `meta` |
|---|---|---|---|---|---|---|
| `login` | `UserLoggedIn` → `AuditUserLogin` | o próprio usuário (do evento) | — | ✅ evento | ✅ evento | — |
| `logout` | **inline** em `AuthController::logout` | `$request->user()` | — | ✅ | ✅ | — |
| `user_deleted` | **inline** em `UserController::destroy` | `$request->user()` | `'User'` (nome curto) | ✅ | ❌ | — |
| `profile_assigned` | `AssignProfilesToUser` → `ProfileAssigned` → `AuditProfileAssignment` | `auth()->id()` | `App\Models\User` (FQCN) | ✅ evento | ❌ | `profile_ids` (conjunto final) |
| `permission_changed` | `SyncMenuPermissions` → `PermissionChanged` → `AuditPermissionChange` | `auth()->id()` | `App\Models\Profile` (FQCN) | ✅ evento | ❌ | `permissions` (matriz final completa) |

**Observado em execução na Fase 3.1:** as três ações originadas por evento
(`login`, `profile_assigned`, `permission_changed`) gravam **duas linhas por
ocorrência**, porque os listeners estão registrados duas vezes
([FIND-002](../findings/README.md#find-002--listeners-de-auditoria-registrados-em-duplicidade)).

## Ações NÃO auditadas

Criação e atualização de usuário; criação, atualização e exclusão de perfil;
criação, atualização e exclusão de menu; criação e revogação de token;
**tentativas de login falhas**; acessos negados (401, 403, 404). Veja
[FIND-012](../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria).

## Desenho: domínio → evento → listener → log

```mermaid
flowchart LR
    Ctrl[Controller] -->|DTO| Act[Action]
    Act -->|1. altera estado| DB[(PostgreSQL)]
    Act -->|2. event, síncrono| Ev[ProfileAssigned /<br/>PermissionChanged]
    Ev --> L[Audit* Listener]
    L -->|auth()->id()| Ctx[(contexto de auth<br/>do processo)]
    L -->|3. INSERT| AL[(audit_logs)]
```

**Por que separar a auditoria em eventos.** A Action publica o fato ("perfis
atribuídos") sem conhecer quem reage a ele. Isso traz:

- **menor acoplamento**: a Action não importa `AuditLog`;
- **extensibilidade**: notificar o usuário afetado ou invalidar cache
  entram como novos listeners;
- **domínio mais estável**: mudar o formato da auditoria não toca a regra de
  negócio.

**Limitações reais do desenho atual:**

| Limitação | Consequência |
|---|---|
| Síncrono, sem transação | Se o listener falhar, o estado já mudou e o cliente recebe 500 sem trilha |
| Ator obtido por `auth()->id()` no listener | Correto hoje, porque roda no mesmo processo da requisição. Um listener enfileirado gravaria `user_id = null` |
| Action obtém IP por `request()->ip()` | A Action não é realmente independente de HTTP |
| IP depende do proxy | Não há configuração de `trustProxies`; atrás de um load balancer seria gravado o IP do balanceador |
| `meta` guarda só o estado final | Não se sabe o que foi **removido**, só o conjunto resultante |
| Metade dos registros ainda é inline | Dois estilos convivem; `subject_type` é inconsistente (`'User'` vs FQCN) |

## Proteção dos próprios logs

- Não há endpoint que leia, altere ou apague `audit_logs`. O menu
  `audit-logs.index` existe no seeder sem rota correspondente.
- A exclusão do ator não apaga logs (INV-14).
- **Não há proteção contra alteração**: o model permite `update()`/`delete()`,
  e o banco não tem trigger nem restrição de privilégio
  ([FIND-014](../findings/README.md#find-014--audit_logs-é-mutável)).

## Dados sensíveis

- Nenhuma senha, token ou hash é gravado em `meta`.
- IP e User-Agent são dados pessoais sob a LGPD. Não há política de retenção
  nem expurgo definida.

## Testes

`tests/Feature/IAM/AuditEventTest.php` executa as requisições reais, sem
`Event::fake()`, e verifica que existe linha com a `action` esperada. O teste
`fires ProfileAssigned event` verifica o dispatch com `Event::fake()`. Nenhum
teste verifica **quantidade**, ator, IP ou `meta`.
