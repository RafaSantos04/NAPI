# Políticas e invariantes de segurança

[← Segurança](README.md) · [Autorização](authorization.md) · [Testes de segurança](../testing/security-tests.md)

> Atualizado na Fase 3.2. Cada invariante alterada tem uma nota **"Fase 3.2"**
> explicando o que mudou em relação à Fase 3.1; o estado anterior e os
> findings que o motivaram continuam em [findings](../findings/README.md).

## Princípios aplicados

### Deny by default

**Confirmado em todas as camadas** ([autorização](authorization.md#deny-by-default)):

- `menu_profiles.can_*` tem `default(false)`, e a ausência de linha também nega;
- `User::hasPermission()` falha fechado para formato, ação ou chave desconhecidos;
- `dev` e `viewer` não têm linhas na matriz e recebem 404 nas áreas administrativas;
- Policies só retornam `true` sob condição explícita.

### Least privilege

| Onde é aplicado | Como |
|---|---|
| Perfis semeados | `viewer` e `dev` sem permissões funcionais (dev faz autoatendimento por `/auth/me`); admin com todas |
| Matriz por ação | view, create, update e delete são flags separadas por área (`menus.key`) |
| Tokens | abilities `read`/`write`/`delete` **aplicadas** por método HTTP; um token só emite tokens com um subconjunto das próprias abilities |
| Tokens | expiração global de 7 dias; `expires_in_days` não passa desse teto |
| Delegação | quem não é admin só concede perfis e flags que já tem |

> **Fase 3.2**: os três pontos em que o least privilege falhava na Fase 3.1
> ([FIND-001](../findings/README.md#find-001--abilities-de-token-não-são-aplicadas),
> [FIND-004](../findings/README.md#find-004--duas-fontes-de-autorização-que-não-se-conhecem),
> [FIND-009](../findings/README.md#find-009--perfil-dev-lista-todos-os-usuários-mas-não-pode-ver-nenhum))
> foram resolvidos.

### Defesa em profundidade

Autenticação, capacidade do token, permissão funcional, regra contextual e
invariante de domínio são camadas independentes, e cada uma só restringe
([ADR-0008](../architecture/decisions/ADR-0008-layered-authorization-model.md)).
No banco, constraints de unicidade e FK, além do lock da invariante de
administrador, são a última camada.

### Não revelar existência

Negação funcional (camada 3) e `viewAny`/`view` respondem 404, o mesmo status
de um recurso inexistente. Regras contextuais em escrita respondem 403;
veja [FIND-020](../findings/README.md#find-020--respostas-de-autorização-e-de-tokens-inconsistentes).

## Catálogo de invariantes

Regras que precisam continuar verdadeiras independentemente da interface: API
atual, futura área Blade ou comandos. "Teste" indica o teste Pest que falharia
se a regra quebrasse.

### INV-01 — Endpoints protegidos exigem autenticação

- **Motivação**: nenhuma operação de IAM é anônima.
- **Aplicação**: grupo `Route::middleware(['auth:sanctum', 'token.ability'])` em `routes/api.php`.
- **Teste**: `returns 401 when accessing protected route without token`, `rejects an invalid bearer token`.
- **Risco mitigado**: acesso anônimo.

### INV-02 — Gerenciar perfis exige a permissão funcional correspondente

- **Motivação**: criar ou alterar perfis é criar privilégio.
- **Aplicação**: `permission:profiles.{create|update|delete}` + `ProfilePolicy` (mesma `hasPermission()`).
- **Teste**: `blocks viewer from creating profile` (404), `lets a custom profile with the permission use the endpoint`, `denies with 404 when the permission is missing`.
- **Fase 3.2**: antes era "só administradores", fixo por slug nas Policies. Agora é concedível pela matriz, e o admin tem a permissão implicitamente.

### INV-03 — Alterar a matriz exige `permissions.update` e não escala privilégio

- **Aplicação**: `permission:permissions.update` + `ProfilePolicy::syncMenus`. Quem não é admin não edita a matriz de um perfil que possui nem concede flags que não tem.
- **Teste**: `PrivilegeEscalationTest › Permission matrix escalation` (6 cenários).
- **Risco mitigado**: auto-concessão de acesso via matriz.
- **Fase 3.2**: a negação para não-admins passou a ter teste. A delegação a perfis customizados é nova e vem limitada pelo que o delegado tem.

### INV-04 — Perfis de sistema são imutáveis pela API

- **Motivação**: `admin`, `dev` e `viewer` são a base do modelo de acesso.
- **Aplicação**: `ProfilePolicy::update/delete/syncMenus` retornam `false` se `is_system`. `is_system` não é aceito em nenhum FormRequest.
- **Teste**: `blocks deletion of system profile` (403), `keeps system profiles immutable, including for admin` (sync, 403).
- **Fase 3.2**: o efeito colateral registrado na Fase 3.1 (o perfil admin não recebe permissões em menus novos) deixou de existir, porque o admin detém todas as permissões funcionais implicitamente.

### INV-05 — Perfil com usuários não pode ser excluído

- **Aplicação**: `ProfilePolicy::delete` (`$profile->users()->exists()`).
- **Teste**: `blocks deletion of profile with users` (HTTP 403) e `prevents deleting a profile that still has users` (Policy direta).
- **Risco mitigado**: revogação silenciosa em massa, já que a FK tem `cascade` em `user_profiles`.

### INV-06 — Menu com filhos, ou de sistema, não pode ser excluído

- **Aplicação**: `MenuPolicy::delete` (`is_system` ou `children()->exists()`).
- **Teste**: `blocks deletion of menu with children` (403), `does not allow deleting a system menu` (403).
- **Risco mitigado**: exclusão em cascata de subárvores, das permissões associadas e das chaves usadas pelas rotas.
- **Fase 3.2**: a proteção de menus de sistema é nova ([FIND-005](../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema)).

### INV-07 — Ninguém exclui a si mesmo

- **Aplicação**: `UserPolicy::delete`.
- **Teste**: `blocks admin from deleting themselves` (403).
- **Risco mitigado**: lockout acidental.

### INV-08 — Existe sempre pelo menos um administrador ativo

- **Definição**: pelo menos um usuário com `is_active = true`, não excluído (soft delete), com o perfil `admin`.
- **Aplicação**: `EnsureActiveAdministratorRemains::beforeRemoving`, chamado **dentro da transação** por `AssignProfilesToUser` (remover o perfil) e `DeleteUser` (excluir o usuário). Ele faz `SELECT ... FOR UPDATE` na linha do perfil `admin`, o que serializa as remoções concorrentes, e conta só administradores ativos. A violação lança `LastActiveAdministratorException`, que responde **409**.
- **Teste**: `AdminInvariantTest` (último ativo, admin inativo não conta, admin excluído não conta, remoção com outro admin ativo, exclusão via Action, nenhuma auditoria em caso de rejeição); `UserProfileSyncTest › blocks removing last admin` (409).
- **Concorrência**: `RefreshDatabase` roda cada teste numa única transação, então um teste Pest com duas conexões simultâneas não é viável. Na Fase 3.2 o bloqueio foi verificado manualmente contra o PostgreSQL: com o lock mantido pela conexão A, a conexão B recebe `55P03 lock_not_available` (com `lock_timeout`) e só adquire o lock depois do commit ou rollback de A.
- **Caminho não coberto**: desativar um usuário (`is_active = false`) não passa pela invariante, porque não existe endpoint de desativação. A Fase 4 deve usar o mesmo guard.
- **Fase 3.2**: antes era check-then-act sem transação, contava admins inativos e respondia 500 ([FIND-006](../findings/README.md#find-006--regra-do-último-administrador-tem-janela-de-corrida-e-resposta-500)). O lockout pela matriz foi eliminado pela imutabilidade de `key` e pela permissão implícita do admin ([FIND-005](../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema)).

### INV-09 — Ninguém concede mais do que tem

- **Aplicação**: `UserPolicy::assignProfiles` e `ProfilePolicy::syncMenus` com `User::holdsPermissionsOf()`. Quem não é admin não altera os próprios perfis, não concede nem retira o perfil `admin`, não mexe em contas de administrador e não adiciona ou remove perfis que concedem algo que não tem.
- **Teste**: `PrivilegeEscalationTest › Profile assignment escalation` (7 cenários).
- **Observação**: um admin pode alterar os próprios perfis (decisão da Fase 3); a proteção relevante é INV-08.

### INV-10 — Listagem e detalhe de usuários obedecem à mesma permissão

- **Aplicação**: `UserPolicy::viewAny` e `view` exigem `users.view`. O autoatendimento é `GET /auth/me`.
- **Teste**: `keeps list and detail consistent for the same permission`, `limits dev to self-service`, `prevents user from viewing other users`, `allows user to view themselves through /auth/me`.
- **Fase 3.2**: antes era "usuário vê apenas a si mesmo, salvo admin", com a exceção do `dev` listando todos ([FIND-009](../findings/README.md#find-009--perfil-dev-lista-todos-os-usuários-mas-não-pode-ver-nenhum)). Com a decisão de manter o dev só em autoatendimento, `dev` recebe 404 em `/users` e em `/users/{id}`, inclusive para o próprio id.

### INV-11 — Entrada do cliente não define atributos de autorização

- **Aplicação**: controllers persistem só `$request->validated()`; nenhum FormRequest aceita `is_system` ou `is_active`; `key` de menu é `prohibited` na atualização; `#[Fillable]` + `Model::shouldBeStrict()`.
- **Teste**: `UserTest › has fillable protection`, `does not accept is_system from the client`, `does not allow changing a menu key`.
- **Risco mitigado**: mass assignment.

### INV-12 — Tokens só são geridos pelo dono

- **Aplicação**: `TokenController` consulta sempre `$request->user()->tokens()`. Token alheio ou inexistente → 404.
- **Teste**: `returns 404 when revoking a token that is not the caller's`.
- **Risco mitigado**: IDOR em credenciais.

### INV-13 — Mudanças de privilégio e de identidade geram exatamente uma entrada de auditoria

- **Aplicação**: eventos `Auditable` → `RecordAuditLog` (um único registro, via discovery), disparados dentro da transação da operação.
- **Teste**: `AuditIntegrityTest` (contagem `=== 1` por ação, ator, pivot `assigned_by`, contexto vindo do evento).
- **Fase 3.2**: a duplicação ([FIND-002](../findings/README.md#find-002--listeners-de-auditoria-registrados-em-duplicidade)) foi corrigida, e a cobertura ganhou usuário e token ([FIND-012](../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria)). CRUD de perfis e menus continua fora; veja [audit.md](audit.md#cobertura).

### INV-14 — A trilha de auditoria sobrevive à exclusão do ator

- **Aplicação**: FK `audit_logs.user_id` com `onDelete('set null')`; usuários usam soft delete.
- **Teste**: `UserTest › force deleting a user nullifies its audit logs instead of deleting them`.

### INV-15 — E-mail é único sem distinção de maiúsculas

- **Aplicação**: coluna `citext` + `unique`.
- **Teste**: `UserTest › email is case-insensitive (citext)`.
- **Risco mitigado**: contas duplicadas e confusão de identidade.

### INV-16 — Usuário inativo não autentica

- **Aplicação**: `AuthController::login` rejeita `is_active = false`; `Sanctum::authenticateAccessTokensUsing` recusa tokens cujo dono está inativo (ponto único de enforcement); o hook `User::updated` apaga os tokens ao desativar (higiene).
- **Teste**: `DisabledUserAuthenticationTest` (5 cenários).
- **Fase 3.2**: antes, apenas novos logins eram barrados ([FIND-003](../findings/README.md#find-003--usuário-desativado-continua-autenticado)).

### INV-17 — Um token nunca excede suas abilities (Fase 3.2)

- **Aplicação**: `EnsureTokenAbility` exige `read`/`write`/`delete` conforme o método HTTP em todas as rotas autenticadas; `TokenStoreRequest::authorize` só emite subconjuntos das abilities do token atual.
- **Teste**: `TokenAbilityTest`.
- **Risco mitigado**: token "somente leitura" vazado usado para destruir ou para emitir um token mais poderoso ([FIND-001](../findings/README.md#find-001--abilities-de-token-não-são-aplicadas)).

### INV-18 — A identidade de uma permissão é estável (Fase 3.2)

- **Aplicação**: `menus.key` único e imutável (`prohibited` na atualização), separado do `route_name` de navegação; menus-chave `is_system` não são excluídos.
- **Teste**: `keeps authorization when a menu route_name or label is renamed`, `does not allow changing a menu key`, `does not allow deleting a system menu`.
- **Risco mitigado**: lockout administrativo por edição de dados de navegação ([FIND-005](../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema)).
