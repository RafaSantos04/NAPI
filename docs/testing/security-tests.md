# Testes de segurança e invariantes

[← Testes](README.md) · [Invariantes](../security/security-policies.md) · [Threat model](../security/threat-model.md)

Os testes abaixo existem para **impedir regressões de segurança**, não para
aumentar cobertura. Cada linha liga uma invariante ao teste que falharia se
ela quebrasse.

## Suíte de regressão da Fase 3.2: `tests/Feature/Security/`

Todos usam **tokens Sanctum reais** (`Authorization: Bearer`) através dos
helpers de `tests/Pest.php`, então cada requisição passa por
`auth:sanctum` → resolução do token → ability → permissão → Policy →
controller. `actingAs()` não faria isso, porque anexa um `TransientToken`
que passa em qualquer ability.

| Helper (`tests/Pest.php`) | Uso |
|---|---|
| `tokenFor($user, $abilities)` | cria um token real e devolve o texto claro |
| `asToken($user, $method, $uri, $data, $abilities)` | envia a requisição com um token novo, depois de `forgetGuards()` (o app de teste mantém o usuário resolvido entre requisições) |
| `seededUser('admin'\|'dev'\|'viewer')` | usuários do seed |
| `profileWithPermissions([...])`, `userWithPermissions([...])` | perfil customizado com exatamente as flags pedidas, por exemplo `['users' => ['view', 'update']]` |

Cada arquivo começou **falhando** contra o código da Fase 3.1 (80 testes: 14
passavam, 28 falhavam e 38 davam erro por esquema ausente), e só passou com a
correção correspondente.

| Arquivo | Testes | Findings | Garante |
|---|---|---|---|
| `TokenAbilityTest` | 14 | FIND-001, 015, 020 | token inválido → 401; `read` permite GET e bloqueia POST/PUT/DELETE; `delete` exigido para DELETE; `write` não passa pela permissão IAM; token não emite token mais poderoso; token de login faz tudo que o dono pode; teto de 7 dias; token alheio → 404 |
| `DisabledUserAuthenticationTest` | 6 | FIND-003 | token de usuário desativado → 401 (mesmo sem limpeza); soft delete → 401; desativar apaga tokens; outras atualizações não; login inativo sem token |
| `AuthorizationMatrixTest` | 23 | FIND-004, 005, 007, 009 | `hasPermission()` real e fail-closed; admin implícito; `is_active` de menu não afeta autorização; perfil customizado funciona via HTTP; listagem e detalhe coerentes; `dev` só autoatendimento; renomear menu não quebra acesso; `key` imutável; menu de sistema não excluível; `is_system` ignorado na entrada |
| `PrivilegeEscalationTest` | 15 | FIND-004, 011, 021 | atribuir perfis sem `users.update`; atribuir a si; conceder `admin`; conceder perfil acima do próprio; rebaixar/editar/excluir administrador; alterar matriz sem permissão; editar a matriz do próprio perfil; conceder flag que não tem; perfil de sistema imutável; menu inexistente → 422; revogar tudo |
| `AdminInvariantTest` | 7 | FIND-006 | último admin ativo → 409; admin inativo e admin excluído não contam; com dois admins ativos a mudança passa; exclusão via Action respeita a invariante; rejeição não gera auditoria |
| `AuditIntegrityTest` | 8 | FIND-002, 012, 013, 017 | `count() === 1` para login, logout, atribuição, matriz, usuário (criar/alterar/excluir) e token (criar/revogar); ator correto; `assigned_by` no pivot; contexto vem do evento, não da sessão |
| `RouteCoverageTest` (Fases 4.2 e 5.1) | 11 | FIND-021, 022 | toda rota `api/*` exceto login tem `auth:sanctum` e `token.ability`; toda rota autenticada da API tem `permission:` ou está na lista explícita de autoatendimento; nenhuma rota da API inicia sessão (`statefulApi` desligado); toda rota `admin*` está no grupo `web`, exige `auth` (exceto landing e login) e `admin.access` (exceto login e logout); toda rota `admin/*` de recurso tem `permission:`; visitante e `dev` são barrados em **todas** as rotas internas do admin |
| `MenuTreeTest` | 7 | FIND-010 | admin vê todos os ativos; inativos (raiz e filho) ocultos; filho sem `view` oculto; filho de pai oculto oculto; `dev` recebe 200 com árvore vazia; mais de um nível; aparecer na árvore não concede acesso |

## Área administrativa: `tests/Feature/Admin/` (Fase 4.2)

`UserManagementTest` (44 testes) prova que a área administrativa usa as
mesmas regras da API:

| Grupo | Garante |
|---|---|
| Acesso | `dev` e `viewer` não abrem sessão nem geram auditoria de login; sessão existente sem acesso é encerrada; quem perde o perfil perde a sessão; perfil customizado com `users.view` entra; permissão de área sem tela não dá entrada; permissão ausente numa página → 404; botões sem permissão não aparecem |
| Listagem e detalhe | perfis carregados antecipadamente; CPF, hash de senha e telefone não aparecem; paginação de 15; busca por nome ou e-mail, sem curingas LIKE; `dev` não lista nem inspeciona; usuário excluído → 404 |
| Criação e edição | senha com hash e fora da auditoria; `is_active`, `is_admin` e `profile_ids` ignorados; mensagens de validação sem eco da senha; `users.create`/`users.update` exigidos (404); não-admin não edita administrador (403); só os campos alterados são auditados |
| Status | desativar revoga tokens (token antigo → 401), remove sessões (`database`) e audita; sessão web existente fica inutilizável; ninguém se desativa (403, sem botão); não-admin não desativa administrador; último admin ativo protegido pela Action; reativar não devolve tokens nem sessões |
| Perfis | `assigned_by` com o ator; formulário vazio remove tudo; autoescalação e concessão de `admin` por delegado → 403; delegado só concede o que tem; último admin → redirect com mensagem e sem auditoria |
| Entre canais | desativação e reativação pela API usam a mesma Action e auditoria; a mesma Policy responde pela API (403/404 e ability `read` → 403); a mesma exceção dá 409 na API e mensagem na web; criação pela API ignora `is_active` |

A sessão web não autentica a API. Isso foi verificado com HTTP real (cookie
→ 401), porque nos testes o session store em memória é compartilhado entre
requisições e a reprodução daria um falso 200. A garantia permanente é
estrutural, em `RouteCoverageTest`.

## Security Lab: `tests/Feature/SecurityLab/` (Fases 5.1 e 5.2)

103 testes (52 na Fase 5.1), mais 3 em `RouteCoverageTest`. Rodam com o
laboratório ligado por `config()`; os testes da feature flag o desligam
explicitamente.

| Arquivo | Testes | Garante |
|---|---|---|
| `SecurityLabAccessTest` | 31 | visitante → landing; usuário do admin sem a permissão → 404 nas cinco rotas; `dev` → sessão encerrada; `security-lab.view` vê os dois testes mas não executa nenhum (404, sem formulário); `admin` pela permissão implícita; perfil só com o laboratório entra no admin e vê só a seção dele; operator desativado perde a sessão. **Flag**: padrão desligado; desligado → 404 até para `admin`, menu oculto, permissão do laboratório não vale como acesso, `RunIdorTest` e `RunMassAssignmentTest` recusam sem gravar nem escrever nada, seeder não cria personas; personas não conseguem autenticar |
| `IdorTestExecutionTest` | 23 | vulnerável → `completed`/`allowed`/`exposed`; protegido, mesmo par → `completed`/`denied`/`protected`; dono lendo o próprio recurso nunca é exposição (nos dois cenários); a interface mostra o conteúdo só quando exposto; matriz da `SecurityLabResourcePolicy` (dono, não dono, `admin` sem exceção); operator ≠ actor gravados; sessão continua com o operator; 1 execução = 1 auditoria, com o operator; conta real como actor e registro de tabela real como alvo são recusados; a FK recusa alvo não sintético; só personas listadas; mensagens de validação; falha técnica → `error`/`inconclusive`; CHECK constraints recusam falha com resultado de segurança |
| `MassAssignmentTestExecutionTest` (Fase 5.2) | 36 | vulnerável → `completed`/`allowed`/`exposed`, com `name` e `is_approved` alteradas; protegido, mesmo payload → `completed`/`denied`/`protected`, só `name` alterada; payload só com a propriedade permitida nunca é exposição (nos dois cenários); a interface marca a alteração indevida só quando exposto; **repetibilidade**: a linha do documento fica idêntica depois de cada cenário, e a sequência vulnerável/protegido/vulnerável/protegido dá sempre o mesmo veredito a partir do mesmo estado; `Model::unguard()` não é usado; operator gravado e actor = dono do alvo; sessão continua com o operator; 1 execução = 1 auditoria, com o operator; registro de tabela real como alvo e propriedade fora do experimento (`owner_user_id`) são recusados; mensagens de validação; alvo inexistente e escrita recusada pelo banco (UNIQUE) → `error`/`inconclusive`, com a execução gravada e o documento intacto; histórico só deste teste, paginado, legível sem o alvo e com o nome do payload escapado; a execução de um teste não aparece como resultado na página do outro; **abrir uma execução pelo histórico** (`?run=`): mesmo resultado de quando rodou, com só `view` e sem executar nada, um único item marcado como atual, painel vazio para id desconhecido, texto qualquer, lista ou execução de outro teste, seleção mantida na paginação, e a execução recém-feita vence a escolhida |
| `SecurityLabHistoryTest` | 13 | lista veredito, cenário, actor, alvo e operator; mais recente primeiro; paginação de 10; relações carregadas antecipadamente; abrir uma execução pelo histórico, com os mesmos casos do Mass Assignment (EXPOSED reexibe o conteúdo sintético, PROTECTED a negação pela Policy); conteúdo exposto não se repete no histórico; execução legível depois de o alvo ser excluído; nomes escapados |
| `RouteCoverageTest` (+3) | — | toda rota `admin/security*` tem `security.lab`; nenhuma recebe identificador de recurso; não há rota do laboratório na API nem pública |

Verificação por mutação (Fase 5.1): os testes falham ao trocar a Policy do
cenário protegido por `true`, ao fazer a Policy permitir todos, ao ignorar o
dono no veredito, ao tirar a flag das rotas, da Action ou da navegação, e ao
tratar erro como `protected`.

Verificação por mutação (Fase 5.2), doze alterações em `RunMassAssignmentTest`
e ao redor, todas detectadas: cenário protegido repassando o payload inteiro;
cenário vulnerável filtrando o payload; flag ignorada pelo caso de uso;
veredito invertido; resposta observada invertida; erro lido como `protected`
(barrado também pelo CHECK do banco); operator trocado pelo actor; sessão
entregue ao actor; `commit` no lugar do `rollBack`; histórico sem filtro por
teste; payload aceitando qualquer chave; rota de execução pedindo só `view`.

## Invariante → teste

| Invariante | Teste | Arquivo |
|---|---|---|
| INV-01 autenticação obrigatória | `returns 401 when accessing protected route without token`, `rejects an invalid bearer token` | `IAM/AuthenticationTest`, `Security/TokenAbilityTest` |
| Rate limit de login | `rate limits login after 6 attempts in 30 minutes` | `IAM/AuthenticationTest` |
| Mensagem uniforme | `rejects invalid password` | `IAM/AuthenticationTest` |
| Logout revoga tokens | `logs out and revokes tokens` | `IAM/AuthenticationTest` |
| INV-02 gerenciar perfis exige permissão | `blocks viewer from creating profile`, `lets a custom profile with the permission use the endpoint`, `denies with 404 when the permission is missing` | `IAM/ProfileTest`, `Security/AuthorizationMatrixTest` |
| INV-03 matriz sem escalação | `Permission matrix escalation` (6) | `Security/PrivilegeEscalationTest` |
| INV-04 perfil de sistema | `blocks deletion of system profile`, `keeps system profiles immutable, including for admin` | `IAM/ProfileTest`, `Security/PrivilegeEscalationTest` |
| INV-05 perfil com usuários | `blocks deletion of profile with users`, `prevents deleting a profile that still has users` | `IAM/ProfileTest`, `IAM/AuthorizationTest` |
| INV-06 menu com filhos ou de sistema | `blocks deletion of menu with children`, `does not allow deleting a system menu` | `IAM/MenuTest`, `Security/AuthorizationMatrixTest` |
| INV-07 auto-exclusão | `blocks admin from deleting themselves` | `IAM/AuthorizationTest` |
| INV-08 admin ativo | `AdminInvariantTest` (7), `blocks removing last admin` (409) | `Security/AdminInvariantTest`, `IAM/UserProfileSyncTest` |
| INV-09 ninguém concede mais do que tem | `Profile assignment escalation` (7), `blocks user from assigning profiles to themselves` | `Security/PrivilegeEscalationTest`, `IAM/UserProfileSyncTest` |
| INV-10 listagem = detalhe | `keeps list and detail consistent…`, `limits dev to self-service`, `prevents user from viewing other users`, `allows user to view themselves through /auth/me` | `Security/AuthorizationMatrixTest`, `IAM/AuthorizationTest` |
| INV-11 entrada não define autorização | `has fillable protection`, `does not accept is_system from the client`, `does not allow changing a menu key`; demonstração em `Mass Assignment scenarios` | `UserTest`, `Security/AuthorizationMatrixTest`, `SecurityLab/MassAssignmentTestExecutionTest` |
| INV-12 tokens do dono | `returns 404 when revoking a token that is not the caller's` | `Security/TokenAbilityTest` |
| INV-13 uma entrada por evento | `AuditIntegrityTest` (8), `leaves no audit trail for a rejected change` | `Security/*` |
| INV-14 trilha sobrevive | `force deleting a user nullifies its audit logs instead of deleting them` | `UserTest` |
| INV-15 e-mail único | `email is unique`, `email is case-insensitive (citext)` | `UserTest` |
| INV-16 inativo não autentica | `DisabledUserAuthenticationTest` (6) | `Security/DisabledUserAuthenticationTest` |
| INV-17 token não excede abilities | `TokenAbilityTest` | `Security/TokenAbilityTest` |
| INV-18 identidade estável de permissão | `Stable permission keys` | `Security/AuthorizationMatrixTest` |
| INV-19 sessão no admin só com acesso | `Admin area access`, `keeps guests and users without admin access out of every internal admin route` | `Admin/UserManagementTest`, `Security/RouteCoverageTest` |
| INV-20 dois adapters, mesmas regras | `Same rules through the API and the admin area`, `does not issue an API token on web login` | `Admin/UserManagementTest`, `Admin/AdminShellTest`, `Security/RouteCoverageTest` |
| INV-21 laboratório só onde foi ligado | `Security Lab feature flag`, `puts every Security Lab route behind the feature flag` | `SecurityLab/SecurityLabAccessTest`, `Security/RouteCoverageTest` |
| INV-22 vulnerável sem endereço, só dado sintético | `Synthetic data only` (dos dois testes), `has no route that serves a lab resource by its identifier`, `does not unguard the models to make the vulnerable write work` | `SecurityLab/*`, `Security/RouteCoverageTest` |
| INV-23 operator real, sessão intacta, falha ≠ proteção | `Operator, actor and session`, `Operational errors` (dos dois testes) | `SecurityLab/IdorTestExecutionTest`, `SecurityLab/MassAssignmentTestExecutionTest` |
| INV-24 teste que escreve não deixa estado | `Repeatability`, `stores the run and restores the document when the database refuses the write` | `SecurityLab/MassAssignmentTestExecutionTest` |
| Integridade de vínculos | testes de `force deleting … cascades` | `UserTest`, `ProfileTest`, `MenuTest` |

## Lacunas

| Lacuna | Motivo / relacionado |
|---|---|
| Corrida concorrente na invariante de administrador | `RefreshDatabase` usa uma única transação por teste, então duas conexões simultâneas não enxergam os dados. O lock foi verificado manualmente no PostgreSQL (INV-08) |
| 401 em cada rota individual de Profiles, Menus e Tokens | INV-01; o grupo é único, e o 401 é testado em `/auth/me` e com token inválido |
| Login falho auditado | adiado (FIND-012) |
| Enumeração no login por tempo | FIND-018, Fase 5 |
| Rate limit das rotas autenticadas | FIND-016 |
