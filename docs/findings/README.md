# Findings: Reality Check da Fase 3.1

[← Documentação](../README.md) · [Threat model](../security/threat-model.md)

Observações encontradas ao investigar o código após a Fase 3. A Fase 3.1 só
documentou. A **Fase 3.2** corrigiu os prioritários e adicionou a cada finding
um bloco **"Status (Fase 3.2)"**, preservando a descrição original, que
registra o comportamento como era ([Fase 3.2](../phases/phase-03-2-iam-hardening.md)).
A **Fase 4.2** acrescentou blocos "Status (Fase 4.2)" onde houve mudança
([Fase 4.2](../phases/phase-04-2-user-management.md)).

Status usados: `OPEN` · `RESOLVED` · `DEFERRED` · `ACCEPTED RISK`. "RESOLVED
(parcial)" indica que o bloco descreve o que foi resolvido e o que continua
aberto ou adiado.

**Evidência "confirmado em execução":** na Fase 3.1, o comportamento foi
reproduzido com testes Pest temporários contra o banco `napi_test`, removidos
logo depois. Na Fase 3.2 essas reproduções viraram testes permanentes em
`tests/Feature/Security/`.
Nos demais casos, a evidência é a leitura do código citado.

**Severidade** só é atribuída quando há impacto técnico concreto:

| Severidade | Critério usado |
|---|---|
| High | Um controle de segurança declarado não funciona e o impacto é explorável |
| Medium | Falha de segurança ou integridade com pré-condição relevante, ou latente com alta chance de ser acionada na próxima fase |
| Low | Impacto limitado, defesa em profundidade ou correção de comportamento |
| Improvement | Consistência, manutenção ou clareza, sem risco direto |

## Resumo

| ID | Título | Categoria | Severidade | Status (Fase 3.2) | Status (Fase 4.2) |
|---|---|---|---|---|---|
| [FIND-001](#find-001--abilities-de-token-não-são-aplicadas) | Abilities de token não são aplicadas | Security | **High** | RESOLVED | = |
| [FIND-002](#find-002--listeners-de-auditoria-registrados-em-duplicidade) | Listeners de auditoria registrados em duplicidade | Architecture | Medium | RESOLVED | = |
| [FIND-003](#find-003--usuário-desativado-continua-autenticado) | Usuário desativado continua autenticado | Security | Medium | RESOLVED | = |
| [FIND-004](#find-004--duas-fontes-de-autorização-que-não-se-conhecem) | Duas fontes de autorização que não se conhecem | Architecture | Medium | RESOLVED | = |
| [FIND-005](#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema) | Menus são chaves de autorização sem proteção de sistema | Security | Medium | RESOLVED | = |
| [FIND-006](#find-006--regra-do-último-administrador-tem-janela-de-corrida-e-resposta-500) | Regra do último administrador tem janela de corrida e resposta 500 | Security | Medium | RESOLVED | = |
| [FIND-007](#find-007--userhaspermission-é-um-stub-que-sempre-autoriza) | `User::hasPermission()` é um stub que sempre autoriza | Security | Medium | RESOLVED | = |
| [FIND-008](#find-008--hash-de-cpf-é-reversível-por-força-bruta) | Hash de CPF é reversível por força bruta | Security | Medium | OPEN | OPEN (deferred) |
| [FIND-009](#find-009--perfil-dev-lista-todos-os-usuários-mas-não-pode-ver-nenhum) | Perfil `dev` lista todos os usuários mas não pode ver nenhum | Security | Medium | RESOLVED | = |
| [FIND-010](#find-010--árvore-de-menus-não-filtra-filhos-nem-inativos) | Árvore de menus não filtra filhos nem inativos | Architecture | Low | RESOLVED | = |
| [FIND-011](#find-011--validação-incompleta-na-sincronização-de-permissões) | Validação incompleta na sincronização de permissões | Maintainability | Low | RESOLVED | = |
| [FIND-012](#find-012--lacunas-de-cobertura-da-auditoria) | Lacunas de cobertura da auditoria | Security | Medium | RESOLVED (parcial) | = |
| [FIND-013](#find-013--contexto-de-auditoria-acoplado-ao-processo-http-e-sem-atomicidade) | Contexto de auditoria acoplado ao processo HTTP e sem atomicidade | Architecture | Low | RESOLVED (parcial) | = |
| [FIND-014](#find-014--audit_logs-é-mutável) | `audit_logs` é mutável | Security | Low | OPEN | = |
| [FIND-015](#find-015--expiração-de-token-aceita-valores-que-nunca-terão-efeito) | Expiração de token aceita valores que nunca terão efeito | Security | Low | RESOLVED (parcial) | = |
| [FIND-016](#find-016--rate-limiting-restrito-ao-login-e-por-emailip) | Rate limiting restrito ao login e por email+IP | Security | Low | OPEN | = |
| [FIND-017](#find-017--assigned_by-não-é-preenchido-pela-api) | `assigned_by` não é preenchido pela API | Maintainability | Low | RESOLVED | = |
| [FIND-018](#find-018--sinais-de-enumeração-no-login) | Sinais de enumeração no login | Security | Low | OPEN | = |
| [FIND-019](#find-019--menu-pode-ser-pai-de-si-mesmo) | Menu pode ser pai de si mesmo | Maintainability | Low | OPEN | = |
| [FIND-020](#find-020--respostas-de-autorização-e-de-tokens-inconsistentes) | Respostas de autorização e de tokens inconsistentes | Maintainability | Improvement | RESOLVED (parcial) | = |
| [FIND-021](#find-021--lacunas-de-testes-de-segurança) | Lacunas de testes de segurança | Testing | Improvement | RESOLVED (parcial) | RESOLVED |
| [FIND-022](#find-022--documentação-e-histórico-divergem-do-código) | Documentação e histórico divergem do código | Documentation | Improvement | RESOLVED (parcial) | RESOLVED |
| [FIND-023](#find-023--análise-estática-ignorava-os-casts-dos-models) | Análise estática ignorava os casts dos models | Maintainability | Improvement | — | — (aberto e resolvido na Fase 5.1) |

Contagem: 1 High · 9 Medium · 9 Low · 4 Improvement. Nenhum Critical.

Após a Fase 5.1: **18 RESOLVED** (o FIND-023 foi aberto e resolvido na própria fase), 4 deles parcialmente, e **5 OPEN** (FIND-008, 014, 016, 018, 019). O Security Lab não abriu finding de segurança; os riscos que ele introduz estão no [threat model](../security/threat-model.md#security-lab).

Após a Fase 4.2: **17 RESOLVED**, agora só 4 deles parcialmente (FIND-021 e FIND-022 passaram de parcial a completo), e **5 OPEN**. O FIND-008 continua aberto e adiado, sem aumento de superfície. Nenhum finding novo foi aberto. Na tabela, `=` indica sem mudança na fase.

Após a Fase 3.2: **17 RESOLVED**, 6 deles parcialmente (o bloco de status diz o que ficou aberto ou adiado), e **5 OPEN** (FIND-008, 014, 016, 018, 019). O único High (FIND-001) e 8 dos 9 Medium foram resolvidos; o Medium restante é o FIND-008.

---

## FIND-001 — Abilities de token não são aplicadas

**Categoria:** Security · **Severidade:** High

### Evidence

- `TokenController::store` aceita `abilities` (`read`, `write`, `delete`) e as grava; o login emite `['read', 'write']`.
- Nenhuma ocorrência de `tokenCan`, `abilities:` ou `ability:` em `app/`, `routes/` ou `bootstrap/`.
- **Confirmado em execução**: um token de admin criado só com `['read']` executou `DELETE /api/v1/users/{id}` com resposta 200.

### Current behavior

O escopo declarado do token é ignorado. Qualquer token tem todo o poder do
seu dono.

### Risk

O controle existe na interface (validação, listagem, mensagem de criação),
então o usuário acredita que limitou o token. Um token "somente leitura"
vazado de um admin permite excluir usuários, alterar perfis e mudar a matriz.

### Recommendation

Aplicar as abilities nas rotas (middlewares `abilities`/`ability` do
Sanctum, com aliases em `bootstrap/app.php`), por exemplo `read` em GET e
`write`/`delete` nas mutações. Alternativa: remover o recurso de abilities
até que seja aplicado. Criar testes com `withToken()`; `actingAs()` não
exercita abilities.

### Suggested phase

Hardening, antes da Fase 4.

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** Sanctum grava as abilities, mas só as verifica quando a rota pede (`abilities:`/`ability:`) ou o código chama `tokenCan()`. Nenhum dos dois existia.

**Resolution:** `EnsureTokenAbility` (alias `token.ability`) aplicado a todo o grupo autenticado, derivando a ability do método HTTP: GET/HEAD/OPTIONS→`read`, POST/PUT/PATCH→`write`, DELETE→`delete`. O login passou a emitir as três abilities. `TokenStoreRequest::authorize` impede um token de emitir outro com abilities maiores que as dele.

**Tests:** `tests/Feature/Security/TokenAbilityTest.php`

**Commit:** `85ed964` · testes em `e6e11c8`

---

## FIND-002 — Listeners de auditoria registrados em duplicidade

**Categoria:** Architecture · **Severidade:** Medium

### Evidence

- `AppServiceProvider::boot()` chama `Event::listen()` para os três listeners.
- O Laravel 13 também os descobre automaticamente em `app/Listeners`.
- `php artisan event:list` mostra cada listener duas vezes (`AuditUserLogin` e `AuditUserLogin@handle`).
- **Confirmado em execução**: um login gera 2 linhas `login`; uma atribuição de perfis gera 2 linhas `profile_assigned`.

### Current behavior

Todo evento auditado é gravado em dobro.

### Risk

A trilha de auditoria perde fidelidade: contagens, detecção de anomalias e
relatórios ficam errados. Qualquer listener futuro com efeito externo
(e-mail, webhook) também rodaria duas vezes.

### Recommendation

Manter um único mecanismo de registro: remover os `Event::listen()` e confiar
na discovery, ou desativar a discovery e manter o registro explícito.
Adicionar teste que verifique `count() === 1`.

### Suggested phase

Hardening. Correção de uma linha; o erro foi introduzido na Fase 3.

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** O Laravel 13 descobre automaticamente listeners em `app/Listeners` (`Class@handle`), e o `AppServiceProvider` os registrava de novo com `Event::listen()` (`Class`). Confirmado em `php artisan event:list`.

**Resolution:** Os `Event::listen()` foram removidos, e a discovery ficou como único mecanismo. Os três listeners foram substituídos por `RecordAuditLog`, que escuta o contrato `Auditable`; `event:list` mostra um único registro.

**Tests:** `tests/Feature/Security/AuditIntegrityTest.php` (`count() === 1` por ação)

**Commit:** `6ba00f0` · testes em `e6e11c8`

---

## FIND-003 — Usuário desativado continua autenticado

**Categoria:** Security · **Severidade:** Medium

### Evidence

- `AuthController::login` rejeita `is_active = false`.
- `auth:sanctum` não verifica `is_active`; nenhum middleware o faz.
- `UserUpdateRequest` não aceita `is_active`, então não há como desativar pela API.
- **Confirmado em execução**: após `is_active = false`, um token emitido antes continuou retornando 200 em `/auth/me`.

### Current behavior

Desativar uma conta impede novos logins, mas não encerra o acesso existente
por até 7 dias.

### Risk

Em um incidente (conta comprometida, desligamento), a desativação não tem
efeito imediato.

### Recommendation

Revogar os tokens ao desativar e/ou recusar requisições de usuários inativos
(middleware ou callback de autenticação do Sanctum). Expor a desativação em
um endpoint próprio, auditado.

### Suggested phase

Hardening / Fase 4 (a área administrativa vai precisar desativar usuários).

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** A guarda do Sanctum valida o token (hash, expiração, `tokenable` existente), mas não conhece o conceito de conta ativa.

**Resolution:** Enforcement num único ponto: `Sanctum::authenticateAccessTokensUsing` no `AppServiceProvider` recusa o token cujo dono não está ativo (401). Higiene: hook `updated` em `User` apaga os tokens quando `is_active` passa a `false`. O endpoint de desativação ficou para a Fase 4.

**Tests:** `tests/Feature/Security/DisabledUserAuthenticationTest.php`

**Commit:** `85ed964` (enforcement), `2392411` (higiene) · testes em `e6e11c8`

### Status (Fase 4.2)

**Status:** RESOLVED (sem mudança). O endpoint adiado foi entregue: `DeactivateUser` (API `POST /users/{id}/deactivate` e área administrativa) revoga os tokens, remove as sessões web e respeita a invariante de administrador; `ActivateUser` não restaura credenciais. **Tests:** `tests/Feature/Admin/UserManagementTest.php` (`Deactivate user`, `Activate user`).

---

## FIND-004 — Duas fontes de autorização que não se conhecem

**Categoria:** Architecture · **Severidade:** Medium

### Evidence

- Matriz orientada a dados: `menu_profiles` + `CheckPermission`.
- Policies orientadas a código: `hasProfile('admin')`/`hasProfile('dev')` em `app/Policies/*`.
- **Confirmado em execução**: um usuário com perfil customizado `editor`, com CRUD completo em `profiles.index` na matriz, recebeu 404 em `GET /profiles` (negado pela Policy).

### Current behavior

O acesso efetivo é a interseção. Perfis novos criados pela API não concedem
praticamente nada, porque as Policies só conhecem os slugs `admin` e `dev`.

### Risk

Funcional e de segurança. O modelo de permissões configurável é, na prática,
fixo em código. Administradores podem acreditar que concederam ou revogaram
algo pela matriz sem efeito real. Duas fontes divergentes são terreno para
brechas quando uma mudar sem a outra.

### Recommendation

Decidir em ADR qual camada é a autoridade para "pode fazer a ação X na área
Y". Uma opção é as Policies consultarem a matriz para a parte baseada em
papel e ficarem só com as regras de instância (sistema, filhos,
auto-exclusão).

### Suggested phase

Antes da Fase 4. A área administrativa depende de perfis configuráveis
funcionarem.

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** As Policies nasceram antes da matriz ter uma API de consulta reutilizável e decidiam por slug. A matriz só existia dentro do `CheckPermission`.

**Resolution:** `User::hasPermission("{key}.{ação}")` é a única resposta funcional, usada pelo middleware, pelas Policies e pela árvore. As Policies ficaram só com regras contextuais e anti-escalação. Decisão registrada no [ADR-0008](../architecture/decisions/ADR-0008-layered-authorization-model.md).

**Tests:** `tests/Feature/Security/AuthorizationMatrixTest.php`, `PrivilegeEscalationTest.php`

**Commit:** `2392411`, `85ed964` · testes em `e6e11c8`

---

## FIND-005 — Menus são chaves de autorização sem proteção de sistema

**Categoria:** Security · **Severidade:** Medium

### Evidence

- `route_name` do menu é a chave usada por `permission:{route_name},...`.
- `MenuPolicy::update/delete` exigem só `admin`; não há `is_system` em menus.
- `ProfilePolicy::syncMenus` bloqueia perfis `is_system`, inclusive `admin`.
- **Confirmado em execução**: o admin renomeou o `route_name` de `menus.index` (200) e em seguida recebeu 404 em `GET /menus`.
- **Confirmado em execução**: `syncMenus` no perfil `admin` responde 403.

### Current behavior

- Um admin pode, pela API, renomear ou excluir (se não tiver filhos) os menus
  que autorizam `users`, `menus` e `permissions`, e perder o acesso a essas
  áreas sem recuperação pela API. Excluir o menu também apaga as permissões em
  cascata.
- Menus criados depois do seed nunca podem ser concedidos ao perfil `admin`
  pela API.

### Risk

Lockout administrativo, a mesma classe de problema que a regra do último
admin tenta evitar, por outro caminho. Também há negação de serviço
autoinfligida.

### Recommendation

Marcar menus-chave como de sistema (imutáveis em `route_name` e não
excluíveis) e/ou desacoplar a chave de autorização do registro de navegação.
Definir como o perfil admin recebe permissões em menus novos, por exemplo com
um bypass explícito de admin na matriz, registrado em ADR.

### Suggested phase

Hardening, antes da Fase 4.

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** O `route_name` fazia dois papéis, navegação (editável) e identificador de permissão, e o perfil `admin` recebia acesso por linhas da matriz que ele mesmo não podia editar.

**Resolution:** Coluna nova `menus.key`, única e imutável (`prohibited` na atualização), usada como identificador de permissão; `route_name` passou a ser só navegação. Os menus-chave são `is_system` e não podem ser excluídos. O `admin` detém todas as permissões funcionais implicitamente, inclusive de menus novos. As colunas entraram na migration de criação de `menus`, já que ainda não há banco de produção.

**Tests:** `tests/Feature/Security/AuthorizationMatrixTest.php` (`Stable permission keys`)

**Commit:** `2392411`, `85ed964`; seed em `dc5066a` · testes em `e6e11c8`

---

## FIND-006 — Regra do último administrador tem janela de corrida e resposta 500

**Categoria:** Security · **Severidade:** Medium

### Evidence

`app/Domain/IAM/Actions/AssignProfilesToUser.php`:

- faz a contagem de outros admins e depois `sync()`, sem `DB::transaction` nem `lockForUpdate`;
- conta usuários com perfil `admin` sem filtrar `is_active`. **Confirmado em execução**: com um segundo admin inativo, o único admin ativo removeu o próprio perfil admin (200);
- lança `\Exception` genérica, e o teste espera 500.

### Current behavior

Protege o caso sequencial simples. Não protege requisições concorrentes nem
o caso de restarem apenas admins inativos.

### Risk

Lockout administrativo em condições específicas. O 500 é tratado como erro de
servidor: vai para os logs como falha, não informa o cliente e, com
`APP_DEBUG=true`, expõe stack trace.

### Recommendation

Executar a checagem e o `sync()` numa transação com lock das linhas
relevantes; contar apenas admins ativos; usar uma exceção de domínio
(ex.: `LastAdministratorException`) renderizada como 409 ou 422, e ajustar o
teste.

### Suggested phase

Hardening.

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** Check-then-act sem transação nem lock; a contagem ignorava `is_active`; a regra lançava `\Exception`.

**Resolution:** Invariante "≥ 1 administrador ativo" em `EnsureActiveAdministratorRemains`, chamada dentro de `DB::transaction` com `SELECT … FOR UPDATE` na linha do perfil `admin`, por `AssignProfilesToUser` e pela nova Action `DeleteUser`. Violação: `LastActiveAdministratorException` → 409, fora do report. A serialização foi verificada manualmente no PostgreSQL (segunda conexão bloqueada com `55P03`).

**Tests:** `tests/Feature/Security/AdminInvariantTest.php`, `IAM/UserProfileSyncTest.php` (500 → 409)

**Commit:** `6ba00f0` · testes em `e6e11c8`

---

## FIND-007 — `User::hasPermission()` é um stub que sempre autoriza

**Categoria:** Security · **Severidade:** Medium

### Evidence

`app/Models/User.php`: `hasPermission(string $routeName, string $action): bool { return true; }`,
com o comentário "Implementado na Fase 2, aqui é só stub". Não há chamadas
hoje.

### Current behavior

Método público de autorização que falha aberto (fail-open).

### Risk

Latente. A próxima fase (área administrativa Blade) tende a precisar de
exatamente essa pergunta ("posso ver este menu?"). Como o nome do método
sugere um controle de acesso, quem o usar vai conceder tudo a todos.

### Recommendation

Implementar reutilizando a mesma consulta do `CheckPermission` (uma única
fonte) ou remover o método.

### Suggested phase

Antes da Fase 4.

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** O stub da Fase 1 nunca foi atualizado quando a lógica real foi escrita no `CheckPermission` (Fase 2).

**Resolution:** `hasPermission(string $permission)` foi implementada de verdade e é agora a fonte única: fail-closed para formato, ação ou chave desconhecidos; admin implícito; resultado carregado uma vez por instância.

**Tests:** `tests/Feature/Security/AuthorizationMatrixTest.php` (`Functional permissions`)

**Commit:** `2392411` · testes em `e6e11c8`

---

## FIND-008 — Hash de CPF é reversível por força bruta

**Categoria:** Security · **Severidade:** Medium

### Evidence

`UserDetails::hashCpf()` = `hash('sha256', dígitos)`, sem salt ou pepper.
`cpf_hash` é `unique`.

### Current behavior

O CPF não é guardado em claro, mas o hash é determinístico sobre um espaço de
cerca de 10⁹ valores válidos.

### Risk

Com acesso ao banco, recuperar todos os CPFs por enumeração leva de minutos a
horas. A pseudonimização prometida pela coluna não resiste. Há impacto LGPD.
Não há exposição pela API hoje: `user_details` não aparece em nenhum Resource.

### Recommendation

Usar HMAC com chave secreta fora do banco (preserva a busca por igualdade e a
unicidade) e/ou criptografia reversível (`encrypted` cast) se o valor
precisar ser lido. Registrar em ADR.

### Suggested phase

Antes de qualquer fase que colete CPF.

### Status (Fase 3.2)

**Status:** OPEN

**Nota:** Fora do escopo da Fase 3.2. Deve ser tratado antes de qualquer fase que colete CPF.

### Status (Fase 4.2)

**Status:** OPEN (DEFERRED). A administração de usuários **não** lê, exibe, busca nem edita CPF, então a Fase 4.2 não aumentou a superfície do finding. A correção recomendada (HMAC-SHA-256 sobre o CPF normalizado, com uma chave própria vinda de configuração, fora do banco e distinta de `APP_KEY` para não acoplar a rotação) exige migração dos hashes existentes e uma decisão sobre rotação da chave. Fica como pré-requisito de qualquer tela que use CPF. **Tests:** `UserManagementTest › lists users with status and profiles, without sensitive data` (o hash não aparece).

---

## FIND-009 — Perfil `dev` lista todos os usuários mas não pode ver nenhum

**Categoria:** Security · **Severidade:** Medium

### Evidence

- `UserPolicy::viewAny` permite `admin` e `dev`; `UserPolicy::view` permite só a si mesmo ou `admin`.
- O `MenuSeeder` dá a `dev` `can_view` em `users.index`, com o comentário "needed for self-service endpoints".
- **Confirmado em execução**: `dev` recebeu 200 em `GET /users` com os 3 usuários (nome, e-mail, estado). O teste `prevents user from viewing other users` mostra que `GET /users/{outro}` retorna 404 para o mesmo `dev`.

### Current behavior

A listagem expõe exatamente os dados que a rota de detalhe esconde.

### Risk

Exposição de dados pessoais (e-mails) a um papel cuja intenção registrada é
autoatendimento. Contradiz a INV-10.

### Recommendation

Alinhar `viewAny` e `view`: restringir `index` a admin ou filtrar a listagem
pelo que o ator pode ver. Adicionar teste de listagem para `dev`.

### Suggested phase

Hardening.

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** `viewAny` e `view` tinham regras diferentes, e o seed dava `users.view` ao `dev` com a intenção de autoatendimento.

**Resolution:** `viewAny` e `view` exigem a mesma permissão, `users.view`. Por decisão do responsável pelo projeto, o `dev` ficou só com autoatendimento (`/auth/me`), e a linha foi removida do seed. `dev` recebe 404 em `/users` e em `/users/{id}`.

**Tests:** `tests/Feature/Security/AuthorizationMatrixTest.php` (`limits dev to self-service`, `keeps list and detail consistent…`)

**Commit:** `85ed964`; seed em `dc5066a` · testes em `e6e11c8`

---

## FIND-010 — Árvore de menus não filtra filhos nem inativos

**Categoria:** Architecture · **Severidade:** Low

### Evidence

`MenuController::tree`: filtra raízes por `can_view` dos perfis do ator,
mas carrega `children` sem filtro de permissão nem de `is_active`, e as raízes
também não são filtradas por `is_active`. Carrega só um nível. A rota exige
`permission:menus.index,view`. **Confirmado em execução**: com
`permissions.index` inativo, ele ainda apareceu como filho de
`profiles.index`.

### Current behavior

- A árvore mostra itens que o usuário não pode acessar e itens desativados.
- Só quem tem permissão de ver **o gerenciamento de menus** (hoje apenas
  admin) recebe a própria navegação; `dev` recebe 404.

### Risk

Não concede acesso, porque as rotas filhas continuam protegidas. Expõe nomes
de áreas e torna o endpoint inútil como navegação para não-admins.

### Recommendation

Filtrar filhos por `can_view` e todos os níveis por `is_active`; tornar
`/menus/tree` acessível a qualquer autenticado, já que ela só devolve o que o
próprio ator vê. Definir a profundidade suportada.

### Suggested phase

Fase 4 (a área administrativa vai consumir a árvore).

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** A árvore filtrava só as raízes, pela matriz, e carregava um nível de filhos sem filtro.

**Resolution:** `MenuController::tree` carrega os menus ativos, filtra por `hasPermission("{key}.view")` e monta a árvore em memória a partir das raízes, em qualquer profundidade. Filhos só aparecem sob pai visível, e ciclos ficam inalcançáveis. A rota ficou acessível a qualquer autenticado com `read`.

**Tests:** `tests/Feature/Security/MenuTreeTest.php`

**Commit:** `6ba00f0`, `85ed964` (rota) · testes em `e6e11c8`

---

## FIND-011 — Validação incompleta na sincronização de permissões

**Categoria:** Maintainability · **Severidade:** Low

### Evidence

`SyncMenusRequest` valida os valores de `permissions.*`, mas não as
**chaves** (IDs de menu).

- **Confirmado em execução**: um ULID de menu inexistente resultou em 500 (violação de FK).
- **Confirmado em execução**: `permissions: []` resulta em 422, porque `required` rejeita array vazio. Não é possível remover todas as permissões de um perfil.
- Não há limite de tamanho do array.

### Risk

Erro de servidor para entrada de cliente, e uma operação legítima
(revogar tudo) impossível.

### Recommendation

Validar as chaves contra `menus.id`, trocar `required` por `present` e
limitar o tamanho.

### Suggested phase

Hardening.

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** As regras validavam só os valores de `permissions.*`.

**Resolution:** `present` em vez de `required` (permite revogar tudo), `max:200` e uma regra que valida as chaves contra `menus.id` (422 em vez de 500).

**Tests:** `tests/Feature/Security/PrivilegeEscalationTest.php` (`rejects unknown menu ids…`, `allows revoking every permission…`)

**Commit:** `85ed964` · testes em `e6e11c8`

---

## FIND-012 — Lacunas de cobertura da auditoria

**Categoria:** Security · **Severidade:** Medium

### Evidence

Auditados: `login`, `logout`, `user_deleted`, `profile_assigned`,
`permission_changed`. Não auditados: criar e atualizar usuário; CRUD de
perfil; CRUD de menu (incluindo mudança de `route_name`, que altera
autorização, [FIND-005](#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema));
criação e revogação de token; login falho. **Confirmado em execução**: criar
um usuário não gerou linha de auditoria.

### Risk

Para um IAM, mudanças em perfis e menus são mudanças de autorização e ficam
sem trilha. Ataques de força bruta não deixam registro na aplicação.

### Recommendation

Definir em ADR o conjunto mínimo de ações auditáveis. Padronizar
`subject_type` (hoje `'User'` em `user_deleted` e FQCN nos listeners) e migrar
logout e exclusão para eventos.

### Suggested phase

Hardening / Fase 5.

### Status (Fase 3.2)

**Status:** RESOLVED (parcial) · restante DEFERRED

**Root cause:** Auditoria implementada caso a caso, sem um conjunto mínimo definido.

**Resolution:** Classificação registrada em [audit.md](../security/audit.md#cobertura). **Audit now**, implementado: `user_created`, `user_updated` (só nomes de campos), `user_deleted` (via evento), `token_created`, `token_revoked`, e `logout` migrado para evento. `subject_type` padronizado em FQCN. **Deferred** (Fase 5): login falho (junto com FIND-016/018) e CRUD de perfis e menus.

**Tests:** `tests/Feature/Security/AuditIntegrityTest.php`

**Commit:** `6ba00f0` · testes em `e6e11c8`

---

## FIND-013 — Contexto de auditoria acoplado ao processo HTTP e sem atomicidade

**Categoria:** Architecture · **Severidade:** Low

### Evidence

- Listeners usam `auth()->id()`; Actions usam `request()->ip()`.
- Nenhuma Action usa transação; o evento é disparado após o `sync()`.
- Não há configuração de `trustProxies` em `bootstrap/app.php`.

### Current behavior

Funciona porque tudo é síncrono e roda dentro da requisição.

### Risk

Enfileirar um listener gravaria ator nulo. Uma falha no listener deixa o
estado alterado sem trilha. Atrás de um proxy, o IP gravado seria o do proxy.

### Recommendation

Carregar ator e IP no evento, recebidos pela Action como contexto explícito,
e envolver estado e auditoria numa transação (ou usar o dispatch de eventos
após o commit). Configurar proxies confiáveis no deploy.

### Suggested phase

Hardening.

### Status (Fase 3.2)

**Status:** RESOLVED (parcial) · `trustProxies` DEFERRED

**Root cause:** Ator e IP eram lidos do processo HTTP (`auth()`, `request()`) em vez de viajar com o fato; o evento era disparado sem transação.

**Resolution:** Eventos auditáveis carregam `AuditContext` (ator, IP, UA) montado na borda; Actions e listeners não usam mais `auth()`/`request()`. As Actions e os controllers disparam o evento dentro de `DB::transaction`, e como o listener é síncrono, estado e trilha são atômicos. `trustProxies` é configuração de deploy e continua pendente.

**Tests:** `tests/Feature/Security/AuditIntegrityTest.php` (`takes the actor from the event…`), `AdminInvariantTest.php` (`leaves no audit trail for a rejected change`)

**Commit:** `6ba00f0` · testes em `e6e11c8`

---

## FIND-014 — audit_logs é mutável

**Categoria:** Security · **Severidade:** Low

### Evidence

`AuditLog` permite `update()`/`delete()`; a migration não cria trigger nem
restrição.

### Risk

Quem obtiver acesso de escrita ao banco, ou um bug futuro, pode alterar ou
apagar a trilha sem deixar vestígio. Hoje nenhum endpoint escreve nela.

### Recommendation

Bloquear `update`/`delete` no model e, no PostgreSQL, revogar UPDATE/DELETE
do papel da aplicação ou usar trigger. Considerar encadeamento por hash para
evidência de adulteração.

### Suggested phase

Fase 5.

### Status (Fase 3.2)

**Status:** OPEN

**Nota:** Fora do escopo da Fase 3.2 (Fase 5).

---

## FIND-015 — Expiração de token aceita valores que nunca terão efeito

**Categoria:** Security · **Severidade:** Low

### Evidence

`config/sanctum.php` define `expiration = 7 dias`, e o Sanctum invalida o
token quando qualquer um dos dois limites é atingido. `TokenController`
aceita `expires_in_days` até 365 e lista `expires_at`. **Confirmado em
execução**: um token com `expires_at` em 30 dias retornou 401 no 8º dia. Não
há `sanctum:prune-expired` agendado.

### Risk

O contrato da API mente sobre a validade real. O efeito é mais seguro, não
menos. Tokens expirados se acumulam no banco.

### Recommendation

Limitar `expires_in_days` ao teto global (ou tornar o teto configurável e
coerente) e agendar a limpeza.

### Suggested phase

Hardening.

### Status (Fase 3.2)

**Status:** RESOLVED (parcial) · limpeza DEFERRED

**Root cause:** A validação de `expires_in_days` ignorava o teto global do Sanctum.

**Resolution:** `TokenStoreRequest` limita `expires_in_days` ao teto derivado de `config("sanctum.expiration")` (7 dias), que também é o padrão. O agendamento de `sanctum:prune-expired` continua pendente.

**Tests:** `tests/Feature/Security/TokenAbilityTest.php` (`caps token lifetime…`)

**Commit:** `85ed964` · testes em `e6e11c8`

---

## FIND-016 — Rate limiting restrito ao login e por email+IP

**Categoria:** Security · **Severidade:** Low

### Evidence

Só `throttle:login` existe, com chave `login:{email}:{ip}`. O grupo `api` não
tem throttle (confirmado por `gatherRouteMiddleware`).

### Risk

Credential stuffing a partir de um IP tem cota nova para cada e-mail.
Endpoints autenticados não têm limite de abuso.

### Recommendation

Adicionar um limite por IP no login, independente do e-mail, e um limiter
padrão para as rotas autenticadas.

### Suggested phase

Hardening.

### Status (Fase 3.2)

**Status:** OPEN

**Nota:** Fora do escopo da Fase 3.2.

---

## FIND-017 — `assigned_by` não é preenchido pela API

**Categoria:** Maintainability · **Severidade:** Low

### Evidence

`AssignProfilesToUser` usa `sync($ids)` sem atributos de pivot.
**Confirmado em execução**: `assigned_by` fica `null`. Só o seeder o
preenche.

### Risk

A relação `assignedBy`/`assignedProfiles` do model fica sem dados em uso
real. A informação existe em `audit_logs` (duplicada; veja FIND-002).

### Recommendation

Passar `assigned_by` ao `sync()` (`syncWithPivotValues`) a partir de um ator
explícito na Action.

### Suggested phase

Hardening.

### Status (Fase 3.2)

**Status:** RESOLVED

**Root cause:** A Action não recebia o ator.

**Resolution:** `AssignProfilesToUser` recebe `AuditContext` e grava `assigned_by` apenas nos perfis recém-anexados, preservando o autor original dos que já existiam.

**Tests:** `tests/Feature/Security/AuditIntegrityTest.php` (`records the assigning actor on the pivot`)

**Commit:** `6ba00f0` · testes em `e6e11c8`

---

## FIND-018 — Sinais de enumeração no login

**Categoria:** Security · **Severidade:** Low

### Evidence

- `LoginRequest` usa `exists:users`: para e-mail inexistente a validação para antes do `Hash::check`, e a resposta é mais rápida.
- `AuthController` responde `Account is inactive.` quando a senha está correta e a conta, inativa.

### Risk

Permite distinguir e-mails cadastrados por tempo de resposta e confirma
credenciais válidas de contas inativas. O rate limit reduz a escala.

### Recommendation

Remover `exists:users`, executar hash mesmo sem usuário e usar mensagem única
também para contas inativas.

### Suggested phase

Fase 5 (bom cenário de verificação para o Security Lab).

### Status (Fase 3.2)

**Status:** OPEN

**Nota:** Fora do escopo da Fase 3.2 (Fase 5).

---

## FIND-019 — Menu pode ser pai de si mesmo

**Categoria:** Maintainability · **Severidade:** Low

### Evidence

`MenuUpdateRequest` valida `parent_id` só com `exists:menus,id`.
**Confirmado em execução**: `PUT /menus/{id}` com `parent_id = id` retornou 200.

### Risk

Ciclos na hierarquia. Hoje a árvore carrega um nível, então não há loop; uma
árvore recursiva futura poderia não terminar.

### Recommendation

Proibir `parent_id` igual ao próprio ID e a descendentes.

### Suggested phase

Fase 4.

### Status (Fase 3.2)

**Status:** OPEN

**Nota:** Fora do escopo. Mitigação parcial: a nova árvore é montada a partir das raízes e nunca alcança nós de um ciclo, então não entra em loop. O dado inválido continua sendo aceito (Fase 4).

### Status (Fase 4.2)

**Status:** OPEN. Fora do escopo: a Fase 4.2 não criou tela de menus.

---

## FIND-020 — Respostas de autorização e de tokens inconsistentes

**Categoria:** Maintainability · **Severidade:** Improvement

### Evidence

- Negação por Policy: 404 em `index`/`show` (checagem manual no controller), 403 via FormRequest e `destroy`.
- `DELETE /tokens/{id}` responde 200 `Token revoked.` mesmo quando nenhum token é apagado.
- `TokenController` valida inline, sem FormRequest, e o parâmetro de rota `{token}` chega como `$tokenId`.
- Formato de resposta: `store` usa `response()->json(Resource::make(...), 201)` e devolve o objeto **sem** envelope; `show`/`update` retornam o Resource direto e devolvem `{"data": {...}}`. Confirmado comparando `json()` com `toResponse()`.

### Risk

Clientes não conseguem tratar erros de forma uniforme; a política de "não
revelar existência" é aplicada pela metade.

### Recommendation

Definir em ADR uma política de resposta (ex.: 404 para qualquer negação a
quem não tem acesso à área; 403 só para regras de instância) e centralizá-la,
por exemplo com `Response::denyAsNotFound()` nas Policies.

### Suggested phase

Hardening.

### Status (Fase 3.2)

**Status:** RESOLVED (parcial) · restante OPEN

**Resolution:** `DELETE /tokens/{id}` responde 404 quando o token não é do usuário ou não existe; a validação de tokens foi para `TokenStoreRequest`, e o parâmetro de rota passou a se chamar `$token`. A política de códigos foi documentada em [authorization.md](../security/authorization.md#códigos-de-resposta): 404 funcional, 403 contextual ou de ability, 409 invariante. Continuam abertos o envelope divergente de `store` e a negação manual em `index`/`show`.

**Tests:** `tests/Feature/Security/TokenAbilityTest.php` (`returns 404 when revoking a token…`)

**Commit:** `85ed964`, `6ba00f0` · testes em `e6e11c8`

### Status (Fase 4.2)

**Status:** sem mudança. A área administrativa não alterou envelopes JSON. Ela segue a mesma política de códigos: página 404 para negação funcional (o `CheckPermission` responde HTML fora da API) e 403 para regra contextual.

---

## FIND-021 — Lacunas de testes de segurança

**Categoria:** Testing · **Severidade:** Improvement

### Evidence

Veja [security-tests.md](../testing/security-tests.md#lacunas). Não há teste
para: abilities de token; login de conta inativa; token de usuário
desativado; revogação de token alheio; negação de `syncMenus`/`update` em
perfil de sistema; negação de sync para não-admin; listagem por `dev`; campos
extras via HTTP; contagem de linhas de auditoria; 401 nas rotas novas.
Todos os testes HTTP usam `actingAs()`, que não passa pelo token.

### Risk

Os FIND-001, FIND-002, FIND-003 e FIND-009 passaram pela suíte verde.

### Recommendation

Cada finding corrigido deve vir com um teste que falhe antes da correção.

### Suggested phase

Fase 5.

### Status (Fase 3.2)

**Status:** RESOLVED (parcial) · varredura de rotas OPEN

**Resolution:** Todos os itens listados na evidência ganharam teste com token real em `tests/Feature/Security/`, com exceção do teste de varredura de rotas. Veja [security-tests.md](../testing/security-tests.md#lacunas).

**Tests:** `tests/Feature/Security/*` (80 testes)

**Commit:** `e6e11c8`

### Status (Fase 4.2)

**Status:** RESOLVED

**Resolution:** `tests/Feature/Security/RouteCoverageTest.php` percorre as rotas registradas e aplica uma regra por área, sem depender da ordem do `route:list`. Na API, toda rota exceto login tem `auth:sanctum` e `token.ability`, e tem `permission:` ou está numa lista explícita de autoatendimento; nenhuma inicia sessão. No admin, toda rota está no grupo `web`, exige `auth` e `admin.access` conforme o papel e, se for de recurso, `permission:`. Além disso, visitante e `dev` são barrados em todas as rotas internas por requisição real. O teste falha quando se remove `admin.access` das rotas de usuários (verificado por mutação).

**Commit:** `8784171`

---

## FIND-022 — Documentação e histórico divergem do código

**Categoria:** Documentation · **Severidade:** Improvement

### Evidence

- O `README.md` descrevia o IAM como "Em construção". **Corrigido na Fase 3.1**, junto com a seção que aponta para `docs/`.
- O commit `7af2c02` menciona "sanctum spa", mas `statefulApi()` não está configurado.
- O stub `User::hasPermission()` ("Implementado na Fase 2, aqui é só stub") entrou no commit `0dc300f`. A lógica real foi implementada depois, no `CheckPermission` (`7af2c02`), e o stub nunca foi atualizado.

### Recommendation

Atualizar o comentário do stub ao tratar o FIND-007. Decidir em ADR se a
autenticação SPA por cookie será ativada (`statefulApi()`) ou abandonada, e
alinhar `config/sanctum.php` à decisão.

### Suggested phase

README: feito na Fase 3.1. Restante: junto com FIND-007 e antes da Fase 4.

### Status (Fase 3.2)

**Status:** RESOLVED (parcial) · decisão SPA OPEN

**Resolution:** O stub de `hasPermission()` foi substituído pela implementação real (FIND-007). A decisão sobre ativar ou abandonar a autenticação SPA por cookie continua pendente.

**Commit:** `2392411` · testes em `e6e11c8`

### Status (Fase 4.2)

**Status:** RESOLVED

**Resolution:** A decisão pendente foi registrada no [ADR-0009](../architecture/decisions/ADR-0009-web-session-and-bearer-adapters.md): a autenticação SPA por cookie não será usada. A área administrativa é Blade com sessão `web`, a API usa só Bearer e `statefulApi()` continua desligado. Um cookie de sessão real recebe 401 na API (verificado por HTTP), e `RouteCoverageTest` impede que uma rota da API passe a iniciar sessão.

**Commit:** `b9185b8` (ADR-0009) · teste em `8784171`

---

## FIND-023 — Análise estática ignorava os casts dos models

**Categoria:** Maintainability · **Severidade:** Improvement

### Evidence

Encontrado na Fase 5.1, ao tipar `SecurityTestRun`. O Larastan só lê o array
devolvido por `casts()` quando `parseModelCastsMethod` está ligado, e o
padrão é desligado. **Confirmado em execução** com `\PHPStan\dumpType()`:
`AuditLog::$meta` (cast `json`) era analisado como `string|null`, e os
atributos com cast para enum, como `string`.

### Current behavior

Todos os models que usam o método `casts()` eram analisados com o tipo cru
da coluna. `composer analyse` passava, mas sem enxergar os tipos reais.

### Risk

Sem risco de segurança. Erros de tipo em atributos com cast (tratar um enum
ou um array como string) não seriam detectados, e código correto com enums
era reportado como erro.

### Recommendation

Ligar `parseModelCastsMethod` no `phpstan.neon`.

### Status (Fase 5.1)

**Status:** RESOLVED

**Root cause:** Opção do Larastan desligada por padrão; o projeto usa o método `casts()` (Laravel 11+) em vez da propriedade `$casts`.

**Resolution:** `parseModelCastsMethod: true` em `phpstan.neon`. A análise do código existente continuou sem erros com os tipos reais.

**Tests:** `composer analyse` (0 erros).

**Commit:** pending (Fase 5.1)
