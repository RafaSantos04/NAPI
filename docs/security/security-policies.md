# Políticas e invariantes de segurança

[← Segurança](README.md) · [Autorização](authorization.md) · [Testes de segurança](../testing/security-tests.md)

## Princípios aplicados

### Deny by default

**Confirmado.** Sem permissão explícita, o acesso é negado:

- `menu_profiles.can_*` tem `default(false)`, e a ausência de linha também nega
  (`CheckPermission`);
- o perfil `viewer` não tem nenhuma linha na matriz e recebe 404 em tudo
  (teste `blocks viewer from accessing user endpoints`);
- Policies só retornam `true` sob condição explícita.

### Least privilege

**Parcialmente aplicado.**

| Onde é aplicado | Como |
|---|---|
| Perfis semeados | `viewer` sem permissões; `dev` apenas `can_view` em `users.index`; só `admin` com CRUD |
| Matriz por ação | view, create, update e delete são flags separadas por área |
| Tokens | expiração global de 7 dias; `expires_in_days` configurável |
| Tokens (pretendido) | abilities `read`/`write`/`delete`, **mas não aplicadas** |

| Onde falha | Finding |
|---|---|
| Um token "somente leitura" executa escritas | [FIND-001](../findings/README.md#find-001--abilities-de-token-não-são-aplicadas) |
| `dev` lista todos os usuários, embora a intenção registrada no seeder seja autoatendimento | [FIND-009](../findings/README.md#find-009--perfil-dev-lista-todos-os-usuários-mas-não-pode-ver-nenhum) |
| Perfis customizados não recebem privilégio granular, porque as Policies exigem `admin` | [FIND-004](../findings/README.md#find-004--duas-fontes-de-autorização-que-não-se-conhecem) |

### Defesa em profundidade

Autenticação, matriz e Policy são independentes; veja
[authorization.md](authorization.md). No banco, constraints de unicidade e
FK funcionam como a última camada.

### Não revelar existência

Falhas de matriz e de `viewAny`/`view` respondem 404, o mesmo status de um
recurso inexistente. A regra é aplicada de forma consistente em leitura e
inconsistente em escrita (403); veja
[FIND-020](../findings/README.md#find-020--respostas-de-autorização-e-de-tokens-inconsistentes).

## Catálogo de invariantes

Regras que precisam continuar verdadeiras independentemente da interface: API
atual, futura área Blade ou comandos. "Teste" indica o teste Pest que falharia
se a regra quebrasse.

### INV-01 — Endpoints protegidos exigem autenticação

- **Motivação**: nenhuma operação de IAM é anônima.
- **Aplicação**: grupo `Route::middleware('auth:sanctum')` em `routes/api.php`.
- **Teste**: `AuthenticationTest › returns 401 when accessing protected route without token` (apenas `/auth/me`).
- **Risco mitigado**: acesso anônimo.

### INV-02 — Só administradores gerenciam perfis

- **Motivação**: criar ou alterar perfis é criar privilégio.
- **Aplicação**: matriz `profiles.index,{create|update|delete}` + `ProfilePolicy::create/update/delete` (`hasProfile('admin')`).
- **Teste**: `ProfileTest › blocks viewer from creating profile` (404).
- **Risco mitigado**: escalação de privilégio.

### INV-03 — Só administradores alteram a matriz de permissões

- **Aplicação**: matriz `permissions.index,update` + `ProfilePolicy::syncMenus`.
- **Teste**: apenas o caminho positivo (`syncs menu permissions`). A negação para não-admins **não tem teste**.
- **Risco mitigado**: auto-concessão de acesso via matriz.

### INV-04 — Perfis de sistema são imutáveis pela API

- **Motivação**: `admin`, `dev` e `viewer` são a base do modelo de acesso.
- **Aplicação**: `ProfilePolicy::update/delete/syncMenus` retornam `false` se `is_system`. `is_system` não é aceito em nenhum FormRequest.
- **Teste**: `blocks deletion of system profile` (403). Update e sync em perfil de sistema **não têm teste** (o 403 do sync foi confirmado em execução na Fase 3.1).
- **Risco mitigado**: adulteração do modelo de acesso base e lockout.
- **Efeito colateral**: o perfil `admin` também não recebe permissões em menus novos ([FIND-005](../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema)).

### INV-05 — Perfil com usuários não pode ser excluído

- **Aplicação**: `ProfilePolicy::delete` (`$profile->users()->exists()`).
- **Teste**: `blocks deletion of profile with users` (HTTP 403) e `prevents deleting a profile that still has users` (Policy direta).
- **Risco mitigado**: revogação silenciosa em massa, já que a FK tem `cascade` em `user_profiles`.

### INV-06 — Menu com filhos não pode ser excluído

- **Aplicação**: `MenuPolicy::delete`.
- **Teste**: `blocks deletion of menu with children` (403).
- **Risco mitigado**: exclusão em cascata de subárvores e das permissões associadas.

### INV-07 — Administrador não exclui a si mesmo

- **Aplicação**: `UserPolicy::delete`.
- **Teste**: `blocks admin from deleting themselves` (403).
- **Risco mitigado**: lockout acidental.

### INV-08 — O último administrador não perde o perfil admin

- **Motivação**: evitar lockout administrativo.
- **Aplicação**: `AssignProfilesToUser::execute`. Se o alvo tem `admin` e o novo conjunto não inclui `admin`, a Action conta outros usuários com `admin` e lança `\Exception` se não houver nenhum.
- **Teste**: `UserProfileSyncTest › blocks removing last admin` (500).
- **Caminhos cobertos**: excluir o último admin é impossível (INV-07, e só admins excluem); excluir ou editar o perfil admin é impossível (INV-04).
- **Limites** ([FIND-006](../findings/README.md#find-006--regra-do-último-administrador-tem-janela-de-corrida-e-resposta-500)):
  - check-then-act sem transação nem lock: duas requisições concorrentes contra admins diferentes podem, cada uma, ver "há outro admin" e zerar os admins;
  - admins com `is_active = false` contam como "restantes";
  - o lockout pela matriz (renomear ou excluir `users.index`, `menus.index`) **não** é coberto ([FIND-005](../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema)).

### INV-09 — Usuário não escala os próprios privilégios

- **Aplicação**: atribuir perfis exige a matriz `users.index,update` (só admin a tem) e `UserPolicy::assignProfiles` (admin). Alterar a matriz exige `permissions.index,update` e `syncMenus` (admin). Criar perfis exige admin.
- **Teste**: `blocks user from assigning profiles to themselves` (404, barrado pela matriz).
- **Observação**: um admin pode alterar os próprios perfis. Isso foi decidido na Fase 3 ([ADR-0004](../architecture/decisions/ADR-0004-policy-based-authorization.md)); um admin já tem o privilégio máximo, e a proteção relevante é INV-08.
- **Caminho indireto analisado**: um não-admin não consegue criar um perfil, dar-lhe permissões e atribuí-lo a si, porque as três etapas exigem admin. Mesmo que conseguisse, as Policies ignorariam o perfil novo (FIND-004).

### INV-10 — Usuário vê apenas a si mesmo, salvo admin

- **Aplicação**: `UserPolicy::view`; `UserController::show` nega com 404.
- **Teste**: `prevents user from viewing other users`, `allows user to view themselves`, `allows admin to view all users`.
- **Exceção**: `GET /users` lista todos para `dev` ([FIND-009](../findings/README.md#find-009--perfil-dev-lista-todos-os-usuários-mas-não-pode-ver-nenhum)).

### INV-11 — Entrada do cliente não define atributos de autorização

- **Aplicação**: controllers persistem só `$request->validated()`; nenhum FormRequest aceita `is_system`, `is_active` ou `profile_ids` no CRUD de usuário; `#[Fillable]` + `Model::shouldBeStrict()`.
- **Teste**: `UserTest › has fillable protection` (no nível do model). **Não há teste HTTP** enviando campos extras.
- **Risco mitigado**: mass assignment.

### INV-12 — Tokens só são geridos pelo dono

- **Aplicação**: `TokenController` consulta sempre `$request->user()->tokens()`.
- **Teste**: só caminhos positivos. Revogar token de outro usuário **não tem teste**.
- **Risco mitigado**: IDOR em credenciais.

### INV-13 — Mudanças de privilégio geram auditoria

- **Aplicação**: `ProfileAssigned` e `PermissionChanged` → listeners → `audit_logs`.
- **Teste**: `AuditEventTest` (verifica `exists()`, o que não detectou a duplicação do [FIND-002](../findings/README.md#find-002--listeners-de-auditoria-registrados-em-duplicidade)).
- **Limite**: CRUD de perfis, menus e usuários não é auditado ([FIND-012](../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria)).

### INV-14 — A trilha de auditoria sobrevive à exclusão do ator

- **Aplicação**: FK `audit_logs.user_id` com `onDelete('set null')`; usuários usam soft delete.
- **Teste**: `UserTest › force deleting a user nullifies its audit logs instead of deleting them`.

### INV-15 — E-mail é único sem distinção de maiúsculas

- **Aplicação**: coluna `citext` + `unique`.
- **Teste**: `UserTest › email is case-insensitive (citext)`.
- **Risco mitigado**: contas duplicadas e confusão de identidade.

### INV-16 — Usuário inativo não obtém novo token

- **Aplicação**: `AuthController::login` rejeita `is_active = false`.
- **Teste**: **nenhum**.
- **Limite**: tokens emitidos antes da desativação continuam válidos ([FIND-003](../findings/README.md#find-003--usuário-desativado-continua-autenticado)).
