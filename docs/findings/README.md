# Findings: Reality Check da Fase 3.1

[← Documentação](../README.md) · [Threat model](../security/threat-model.md)

Observações encontradas ao investigar o código após a Fase 3. **Nenhuma foi
corrigida nesta fase**: a Fase 3.1 só documenta.

**Evidência "confirmado em execução":** o comportamento foi reproduzido com
testes Pest temporários contra o banco `napi_test`, removidos logo depois.
Nos demais casos, a evidência é a leitura do código citado.

**Severidade** só é atribuída quando há impacto técnico concreto:

| Severidade | Critério usado |
|---|---|
| High | Um controle de segurança declarado não funciona e o impacto é explorável |
| Medium | Falha de segurança ou integridade com pré-condição relevante, ou latente com alta chance de ser acionada na próxima fase |
| Low | Impacto limitado, defesa em profundidade ou correção de comportamento |
| Improvement | Consistência, manutenção ou clareza, sem risco direto |

## Resumo

| ID | Título | Categoria | Severidade |
|---|---|---|---|
| [FIND-001](#find-001--abilities-de-token-não-são-aplicadas) | Abilities de token não são aplicadas | Security | **High** |
| [FIND-002](#find-002--listeners-de-auditoria-registrados-em-duplicidade) | Listeners de auditoria registrados em duplicidade | Architecture | Medium |
| [FIND-003](#find-003--usuário-desativado-continua-autenticado) | Usuário desativado continua autenticado | Security | Medium |
| [FIND-004](#find-004--duas-fontes-de-autorização-que-não-se-conhecem) | Duas fontes de autorização que não se conhecem | Architecture | Medium |
| [FIND-005](#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema) | Menus são chaves de autorização sem proteção de sistema | Security | Medium |
| [FIND-006](#find-006--regra-do-último-administrador-tem-janela-de-corrida-e-resposta-500) | Regra do último administrador tem janela de corrida e resposta 500 | Security | Medium |
| [FIND-007](#find-007--userhaspermission-é-um-stub-que-sempre-autoriza) | `User::hasPermission()` é um stub que sempre autoriza | Security | Medium |
| [FIND-008](#find-008--hash-de-cpf-é-reversível-por-força-bruta) | Hash de CPF é reversível por força bruta | Security | Medium |
| [FIND-009](#find-009--perfil-dev-lista-todos-os-usuários-mas-não-pode-ver-nenhum) | Perfil `dev` lista todos os usuários mas não pode ver nenhum | Security | Medium |
| [FIND-010](#find-010--árvore-de-menus-não-filtra-filhos-nem-inativos) | Árvore de menus não filtra filhos nem inativos | Architecture | Low |
| [FIND-011](#find-011--validação-incompleta-na-sincronização-de-permissões) | Validação incompleta na sincronização de permissões | Maintainability | Low |
| [FIND-012](#find-012--lacunas-de-cobertura-da-auditoria) | Lacunas de cobertura da auditoria | Security | Medium |
| [FIND-013](#find-013--contexto-de-auditoria-acoplado-ao-processo-http-e-sem-atomicidade) | Contexto de auditoria acoplado ao processo HTTP e sem atomicidade | Architecture | Low |
| [FIND-014](#find-014--audit_logs-é-mutável) | `audit_logs` é mutável | Security | Low |
| [FIND-015](#find-015--expiração-de-token-aceita-valores-que-nunca-terão-efeito) | Expiração de token aceita valores que nunca terão efeito | Security | Low |
| [FIND-016](#find-016--rate-limiting-restrito-ao-login-e-por-emailip) | Rate limiting restrito ao login e por email+IP | Security | Low |
| [FIND-017](#find-017--assigned_by-não-é-preenchido-pela-api) | `assigned_by` não é preenchido pela API | Maintainability | Low |
| [FIND-018](#find-018--sinais-de-enumeração-no-login) | Sinais de enumeração no login | Security | Low |
| [FIND-019](#find-019--menu-pode-ser-pai-de-si-mesmo) | Menu pode ser pai de si mesmo | Maintainability | Low |
| [FIND-020](#find-020--respostas-de-autorização-e-de-tokens-inconsistentes) | Respostas de autorização e de tokens inconsistentes | Maintainability | Improvement |
| [FIND-021](#find-021--lacunas-de-testes-de-segurança) | Lacunas de testes de segurança | Testing | Improvement |
| [FIND-022](#find-022--documentação-e-histórico-divergem-do-código) | Documentação e histórico divergem do código | Documentation | Improvement |

Contagem: 1 High · 9 Medium · 9 Low · 3 Improvement. Nenhum Critical.

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
