# Fase 5.1 — Security Lab Foundation + IDOR/BOLA Laboratory

[← Fases](README.md) · [Módulo Security Lab](../modules/security.md) · [IDOR / BOLA](../security/idor.md) · [ADR-0010](../architecture/decisions/ADR-0010-controlled-security-lab.md)

**Commit:** pending

## Objetivo

Abrir o domínio Security com um laboratório **controlado**: seguro,
auditável, baseado em dados sintéticos, integrado ao IAM real e capaz de
mostrar lado a lado o comportamento vulnerável e o protegido. O primeiro
teste é IDOR/BOLA. O objetivo não era criar uma página vulnerável.

## Reality check

Estado encontrado antes de implementar:

| Pergunta | Encontrado |
|---|---|
| Existe `app/Domain/Security`? | Não. Só `app/Domain/IAM` |
| Existe recurso sintético ou tabela de execuções? | Não |
| Existe auditoria reutilizável? | Sim: contrato `Auditable` + `RecordAuditLog`, um registro por evento, na transação da operação (Fase 3.2) |
| Existe contexto de ator? | Sim: `AuditContext` (ator, IP, UA) |
| Existe middleware de permissão? | Sim: `permission:{key}.{ação}`, que já responde página 404 na web (Fase 4.2) |
| Existe navegação por permissão? | Sim: `AdminNavigation`, que também decide a entrada no admin |
| Existe feature flag ou `config/security.php`? | Não |
| Existem enums no projeto? | Não |
| Que ações a matriz tem? | `view`, `create`, `update`, `delete`. Não há `execute` |
| Documentação prévia do módulo? | `docs/modules/security.md` dizia "planejado, sem código" e prometia confirmar as fronteiras em ADR |
| Estado da 4.2 | Commitada (`c797865`…`439b18f`), mas ainda marcada `pending` nos documentos |

## Engineering Gate

Cada componente passou pelas três perguntas: precisa existir, já existe, o
framework, a linguagem ou o banco já resolvem.

| Componente | Precisa existir? | Já existia? | Framework/PHP/PostgreSQL resolvia? | Decisão |
|---|---|---|---|---|
| Migrations (2) | Sim: recurso sintético e execuções | Não | Schema Builder; CHECK por `DB::statement` (o builder não tem) | **Criadas** |
| `SecurityLabResource` | Sim: o alvo não pode ser tabela real | Não | Eloquent + `HasUlids` | **Criado** |
| `SecurityTestRun` | Sim: registro do que o teste observou | Não (`audit_logs` responde outra pergunta) | Eloquent, casts para enum, `jsonb` | **Criado** |
| Tabela `idor_tests` | Não: nada exige constraint ou relação própria | — | `result_context` em `jsonb` | **Não criada** |
| Tabela `security_test_events` | Não: o histórico sai das execuções | — | — | **Não criada** |
| Colunas `target_type`, `expected_outcome`, `input_context`, `duration_ms`, `started_at`, `finished_at`, `updated_at` | Não: redundantes ou deriváveis | — | FK fixa o tipo do alvo; `created_at` do banco | **Não criadas** ([schema](../database/schema.md#security_test_runs-fase-51)) |
| DTO `RunIdorTestDto` | Sim: fronteira HTTP → caso de uso, com enum já convertido | Padrão existente (`app/DTOs`, ADR-0007) | `readonly` | **Criado** |
| Action `RunIdorTest` | Sim: caso de uso real, e único lugar do cenário vulnerável | Padrão existente (`execute()` estático) | `Gate::forUser()`, `DB::transaction` | **Criada** |
| Service / Manager | Não: mesma responsabilidade da Action | — | — | **Não criado** |
| `SecurityLabResourcePolicy` | Sim: é o controle demonstrado | Não | Policy nativa | **Criada** |
| `SecurityTestRunPolicy` | Sim: navegação e FormRequest pedem ability + subject | Padrão das outras Policies (`hasPermission`) | Policy nativa | **Criada** |
| Evento `SecurityTestExecuted` | Sim: o fato precisa de trilha | Contrato `Auditable` reutilizado | Eventos nativos | **Criado** (um só) |
| Eventos por passo (started, target selected…) | Não: sem consumidor | — | — | **Não criados** |
| Listener | Não | `RecordAuditLog` já escuta `Auditable` | — | **Reutilizado** |
| Enums (5) | Sim: conjuntos fechados persistidos | Não | Enums nativos + cast + `Rule::enum` | **Criados** |
| Middleware `EnsureSecurityLabEnabled` | Sim: a flag precisa valer por requisição e ser testável | Não | Registrar rotas condicionalmente dependeria do cache de rotas | **Criado** |
| Middleware de permissão | Não | `permission:` | — | **Reutilizado** |
| FormRequest `RunIdorTestRequest` | Sim: validação + delegação à Policy | Padrão existente | `exists`, `ulid`, `Rule::enum` | **Criado** |
| Controller `IdorTestController` | Sim: página e execução | Não | — | **Criado**, fino |
| Controller da landing | Não | — | `Route::view` | **Não criado** |
| `config/security.php` | Sim: flag fora de `env()` | Não | `config()` | **Criado** |
| Exception própria para laboratório desligado | Não: é erro de quem chama | `LogicException` já usada na invariante do IAM | PHP | **Não criada** |
| Ação `execute` na matriz | Não: executar cria uma execução | Ação `create` | — | **Não criada** |
| Coluna/flag de persona em `users` | Não | Persona = dona de recurso sintético | `Rule::exists` | **Não criada** |
| Relação `User::labResources()` | Não: o IAM não precisa conhecer o laboratório | — | Subquery `whereIn` | **Não criada** |
| Factory `SecurityLabResourceFactory` | Sim: testes | Padrão existente | — | **Criada** |
| Factory de `SecurityTestRun` | Não: os testes usam o caso de uso real | — | — | **Não criada** |
| `SecurityLabSeeder` | Sim: ambiente de demonstração | Não | `firstOrCreate` | **Criado**, idempotente |
| Interface, classe base, registry, pipeline de testes | Não: há um caso concreto só | — | — | **Não criados** |
| Repository | Não | — | Eloquent | **Não criado** |
| API Resource | Não: não há endpoint JSON | — | — | **Não criado** |
| Layout, CSS, paginação, flash | Não | `layouts.admin-shell`, `admin.css`, parcial de paginação | — | **Reutilizados** (CSS estendido) |

Resultado: **15 classes de aplicação** novas (2 models, 5 enums, 2 Policies,
DTO, Action, evento, middleware, FormRequest e controller), cada uma com uma
responsabilidade própria, e nenhuma abstração sem uso.

## Arquitetura

```text
Browser → web (sessão, CSRF, EnsureUserIsActive)
        → auth → admin.access → security.lab (flag, 404) → permission:security-lab.* (404)
        → IdorTestController
             → RunIdorTestRequest (valida; authorize() → SecurityTestRunPolicy::create)
             → RunIdorTestDto
             → RunIdorTest
                  1. flag ligada?                        (senão LogicException)
                  2. resolve actor e alvo sintético
                  3. cenário:  vulnerável → devolve
                               protegido  → Gate::forUser($actor)->allows('view', $alvo)
                  4. resposta observada + veredito       (erro técnico → inconclusive)
                  5. DB::transaction
                       ├ INSERT security_test_runs
                       └ SecurityTestExecuted → RecordAuditLog → INSERT audit_logs
        → redirect (PRG) → página com o resultado e o histórico
```

A leitura do cenário fica fora da transação. Só a execução e a auditoria
dela, que são um fato só, são gravadas juntas. Não se usa `afterCommit()`: o
listener de auditoria é síncrono e está dentro da transação, que é o padrão
do NAPI desde a Fase 3.2.

## Banco de dados

| Tabela | Constraints e índices |
|---|---|
| `security_lab_resources` | PK ULID; FK `owner_user_id` → `users` (cascade); UNIQUE `(owner_user_id, name)` |
| `security_test_runs` | PK ULID; FKs `initiated_by_user_id`, `acting_as_user_id` → `users` e `target_resource_id` → `security_lab_resources` (todas set null, indexadas); índice `(test_key, created_at)`; 4 CHECK de conjunto fechado e 2 de coerência entre status, resposta e veredito |

Detalhes e colunas descartadas em [schema.md](../database/schema.md#security_test_runs-fase-51).
Migrations verificadas com `migrate:fresh`, `migrate:rollback --step=2`,
`migrate` e `migrate:fresh --seed` no banco `napi_test`.

O menu de sistema `security-lab` foi acrescentado ao `MenuSeeder`, que passou
a poder ser rodado de novo. Para um banco existente:

```bash
php artisan migrate
php artisan db:seed --class=MenuSeeder
# com SECURITY_LAB_ENABLED=true no .env:
php artisan db:seed --class=SecurityLabSeeder
```

## Fronteiras de segurança

| Camada | Como |
|---|---|
| Feature flag | `SECURITY_LAB_ENABLED` (padrão `false`) → `config('security.lab.enabled')`. Conferida no middleware, na navegação, na Action e no seeder |
| Autenticação | sessão `web`; usuário inativo perde a sessão |
| Permissão | `security-lab.view` e `security-lab.create`, pela matriz. `admin` pela permissão implícita; nenhum teste por slug de perfil |
| Dados sintéticos | alvo só `SecurityLabResource` (validação + FK); actor só persona |
| Sem URL vulnerável | nenhuma rota do laboratório recebe identificador de recurso |
| Sessão | o actor é avaliado com `Gate::forUser()`; a sessão não muda de dono |

## Cenário IDOR

| | |
|---|---|
| Operator | quem está autenticado (ex.: `admin@napi.dev`) |
| Actor | Alice (persona) |
| Target | Documento B, de Bob (persona) |
| Vulnerável | recurso encontrado pelo id e devolvido → `completed` · `allowed` · **EXPOSED** |
| Protegido | mesma busca + `SecurityLabResourcePolicy::view` → `completed` · `denied` · **PROTECTED** |
| Dono (Alice + Documento A) | `allowed` · **PROTECTED**, "acesso legítimo do dono", nos dois cenários |
| Falha técnica | `error` · **INCONCLUSIVE** |

Explicação completa em [idor.md](../security/idor.md).

## Auditoria

`audit_logs` registra **quem fez o quê**: `security_test_executed`, com o
operator real como ator e a execução como subject. `security_test_runs`
registra **o que o teste observou**: cenário, actor, alvo, resposta e
veredito. Uma execução gera exatamente uma linha em cada tabela, na mesma
transação. Nenhuma das duas guarda senha, token, id de sessão nem o conteúdo
exibido.

## Interface

`/admin/security` (apresentação e lista de testes) e `/admin/security/idor`,
em três colunas: configuração do teste, resultado da última execução e
histórico. Actor, recurso alvo e cenário são grupos de `radio` (o cenário, em
controle segmentado), sem JavaScript. O histórico fica numa caixa de altura
fixa com rolagem própria, focável pelo teclado, e continua paginado. Com
menos largura o resultado desce para baixo do formulário; em uma coluna só
ele sobe para o topo e some enquanto está vazio. Depois de executar, o par
actor/alvo fica selecionado para rodar o outro cenário. Quem só tem `view` vê
o histórico e um aviso no lugar do formulário. As barras de rolagem da área
administrativa são finas (`scrollbar-width: thin`).

**Checkpoint visual**: servidor real sobre `napi_test` com a flag ligada e
outro com a flag desligada; fluxos exercidos por HTTP (menu, execução
vulnerável e protegida, caso do dono, erros de validação, CSRF 419, usuário
só-leitura, `dev` recusado, 404 com a flag desligada) e páginas renderizadas
no Edge headless a 1280 px e num iframe de 390 px. Não foi feito teste
manual num navegador interativo (foco por teclado no controle segmentado).

## Testes

| Arquivo | Testes |
|---|---|
| `tests/Feature/SecurityLab/SecurityLabAccessTest.php` (novo) | 22 |
| `tests/Feature/SecurityLab/IdorTestExecutionTest.php` (novo) | 23 |
| `tests/Feature/SecurityLab/SecurityLabHistoryTest.php` (novo) | 7 |
| `tests/Feature/Security/RouteCoverageTest.php` | +3 (fronteira do laboratório) |

O que cada um garante está em [security-tests.md](../testing/security-tests.md#security-lab-testsfeaturesecuritylab-fase-51).
Sete mutações do código foram detectadas pela suíte.

### Testes antigos alterados

| Teste | Antes | Depois | Motivo |
|---|---|---|---|
| `MenuTreeTest › shows admin every active menu…` e `hides inactive roots…` | 4 raízes | + `security-lab` | novo menu de sistema no seed |

## Outras mudanças

- **Larastan**: `parseModelCastsMethod: true` em `phpstan.neon`. Sem isso os
  atributos com cast eram analisados como o tipo cru da coluna
  ([FIND-023](../findings/README.md#find-023--análise-estática-ignorava-os-casts-dos-models)).
- **Status da 4.2**: hashes reais no lugar de `pending` em `README.md` das
  fases, na própria fase e nos FIND-021/022.
- **Regra de engenharia** registrada como permanente em
  [project-structure.md](../architecture/project-structure.md#regra-de-engenharia-permanente-desde-a-fase-51).

## Findings

| Finding | Na Fase 5.1 |
|---|---|
| FIND-023 | **Novo e RESOLVED**: análise estática ignorava os casts |
| FIND-008, 014, 016, 018, 019 | continuam OPEN, fora do escopo |
| Segurança do laboratório | sem finding; riscos e mitigações no [threat model](../security/threat-model.md#security-lab) |

## Fora do escopo

Outros testes (mass assignment, escalação de privilégio, abilities de token,
rate limiting, CSRF); abstração comum de testes; API do laboratório; limpeza
ou exportação do histórico; viewer de `audit_logs`.
