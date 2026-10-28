# P2 — Projects, Applications & Environments

[← Produto](../README.md) · [Roadmap](../roadmap.md) · [ADR-0012](../../architecture/decisions/ADR-0012-project-tree-tenant-integrity.md) · [P1](p1-organizations-tenancy.md)

**Status:** implementada e validada · **JUDGE:** concluído · **Commits:** `0ce81ef` a `1adb60f`

## Executive Summary

P2 cria a primeira árvore de dados comerciais sob a Organization:
Project → Application → Environment. O isolamento de tenant deixa de depender
só de HTTP: toda tabela carrega `organization_id` e referencia o pai, o
criador e o responsável por **FK composta**, e um trigger impede mover a linha
de tenant ou de pai. Nenhuma combinação de SQL direto cria uma árvore
cross-tenant.

Arquivar substitui excluir. Um ancestral arquivado deixa a subárvore somente
leitura, com locks de cima para baixo. O papel `manager` e a regra "quem
criou o Project arquiva a árvore" respondem a quem pode arquivar sem criar
uma segunda matriz de permissões. O perfil global `Admin` continua sem poder
dentro de tenants.

## Reality Check

Antes do P2 existiam Organization, Membership, contexto por rota,
`User::hasPermission()` contextual com `owner|member` e 4 permissions,
auditoria atômica e append-only, e o padrão FormRequest → DTO → Action →
Resource. Não existia nenhum agregado comercial, coluna de tenant fora de
memberships/audit, nem regra de arquivamento.

O plano original concentrava as três APIs no T04. A execução foi por slices
verticais (passo 0 de fundação, depois Project, Application e Environment
completos), e o T05 ficou como auditoria adversarial transversal.

## Escopo entregue

| Etapa | Entrega | Commits |
|---|---|---|
| Passo 0 | papel `manager`; 15 permissions da árvore; `UNIQUE(organization_id, id)` em memberships; função `napi_prevent_tenant_reassignment()`; envelope `{"data"}` em todos os `store` (FIND-020) | `0ce81ef`, `8575046`, `c07055e` |
| Slice 1 | Project com criador imutável e lifecycle | `6619514`, `126f5af`, `2726e3e`, `e8781a9` |
| Slice 2 | Application com FK composta para Project e ancestral arquivado | `b034c1e`, `218619c`, `62f1789`, `bb47cbe` |
| Slice 3 | Environment com responsável, classificação fechada e escopo autorizado | `5b8868a`, `08a5f85`, `e7cb877`, `29835bb` |
| T05 | auditoria adversarial e trilha das 12 escritas | `f527314`, `d734a27` |
| T06 | JUDGE, relatório, ADR aceito, docs | `1adb60f` |

## Data Model

```text
Organization ──┬── OrganizationMembership (owner | manager | member)
               │          ▲ (organization_id, created_by_membership_id)
               ├── Project ┘
               │     ▲ (organization_id, project_id)
               ├── Application
               │     ▲ (organization_id, application_id)
               └── Environment ── (organization_id, responsible_membership_id) → Membership
```

| Tabela | Unicidade | Imutável (trigger) | Lifecycle |
|---|---|---|---|
| `projects` | `slug` por organização | `organization_id`, `created_by_membership_id` | `archived_at` |
| `applications` | `slug` por project | `organization_id`, `project_id` | `archived_at` |
| `environments` | `slug` por application; `classification` com CHECK | `organization_id`, `application_id` | `archived_at` |

Colunas de FK composta são `NOT NULL`: no `MATCH SIMPLE` do PostgreSQL, uma
coluna nula desliga a verificação. O UNIQUE do slug no filho também indexa a
FK. FKs são `restrict`; não há SoftDeletes nem DELETE. Detalhes em
[schema.md](../../database/schema.md).

## Authorization

```text
User::hasPermission(permission, organization da rota)
  → Policy (cadeia environment → application → project → organization,
            regra archive × archive_own pelo criador do Project)
  → Action (ancestral arquivado, responsável ativo, locks)
```

| Papel | Organização e membros | Árvore de projetos |
|---|---|---|
| owner | ver, alterar, gerir | tudo, inclusive arquivar qualquer item |
| manager | ver | tudo, inclusive arquivar qualquer item |
| member | ver | ver, criar, alterar; arquivar só a árvore de um Project que criou |

Rotas de archive/restore exigem `*.archive_own`, que todo papel capaz de
arquivar possui; a Policy decide o alcance. Negação funcional e tenant
estrangeiro respondem 404; regra contextual (member arquivando o trabalho
alheio) responde 403; ancestral ou estado arquivado, 409.

## Lifecycle e concorrência

`active ⇄ archived`. Arquivar um Project não reescreve os filhos: a subárvore
fica somente leitura e volta inteira no restore. Restaurar exige restaurar
antes os ancestrais.

Toda escrita trava os ancestrais com `FOR SHARE`, de cima para baixo, e a
linha alterada com `FOR UPDATE`. Escritas em filhos diferentes não se
bloqueiam; um arquivamento concorrente do ancestral espera por elas. A ordem
fixa elimina deadlock entre escritas da árvore.

## Environment

- `classification`: `production | staging | development | other`, fechada
  porque fases futuras (P10) dependem dela; nome e slug são livres.
- `authorized_scope` (obrigatório) e `authorization_reference`: registro
  humano e auditável. Não é allowlist; o NAPI não faz requisições ao escopo.
- Responsável: opcional no payload (padrão = quem cria), precisa ser
  membership ativa (validação + revalidação na Action sob lock), trocável por
  `environments.update`. Ser responsável não concede arquivamento.

## API

18 rotas sob `/organizations/{organization}`, aninhadas com scoped binding em
cadeia: `projects/{project}/applications/{application}/environments/{environment}`.
Listagens com `status` (`active` padrão, `archived`, `all`), `search` com
curingas tratados como texto e, em environments, `classification`. Contrato
em [api/v1.md](../../api/v1.md).

## Audit

12 ações novas (`project_*`, `application_*`, `environment_*`: created,
updated, archived, restored), sempre com `organization_id`, ator e subject,
disparadas dentro da transação da Action. `*_updated` registra só nomes de
campos; o conteúdo de `authorized_scope` nunca entra na trilha. Ver
[audit.md](../../security/audit.md).

## Tests e mutações

| Arquivo | Testes |
|---|---|
| `Unit/Domain/Tenancy/Enums/OrganizationRoleTest.php` | 20 (matriz papel × permission escrita à mão) |
| `Feature/Projects/` | 37 |
| `Feature/Applications/` | 36 |
| `Feature/Environments/` | 49 |
| `Feature/Security/ProjectTreeSecurityTest.php` (T05) | 53 |
| `Feature/Security/RouteCoverageTest.php` | +2 (scoped binding, sem DELETE) |
| `Feature/Tenancy/*` | +7 (manager, imutabilidade de membership) |

Total: 204 testes novos sobre os 424 do P1.

Cada camada tem um teste próprio: SQL direto para FK, trigger e CHECK; Policy
chamada diretamente para provar que ela não depende do binding; HTTP para o
fluxo; e uma matriz guiada pelas rotas, que cobre automaticamente rotas
futuras sob `organizations.projects.*`.

Mutações executadas e revertidas (19), cada uma derrubando ao menos um teste:

| Etapa | Mutação |
|---|---|
| Passo 0 | manager com `members.manage`; trigger sem `organization_id` |
| Project | regra do criador removida; FK composta → simples; escape do `search` removido |
| Application | FK composta → simples; checagem de project arquivado (create e restore); checagem de `project_id` na Policy; regra do criador |
| Environment | as duas FKs compostas → simples; revalidação do responsável; application arquivada; checagem de application na Policy; regra do criador |
| T05 | `scopeBindings()` removido (a Policy ainda nega com 403: duas barreiras independentes); evento disparado após a transação; `PATCH` gravando o payload inteiro |

## Engineering Gate

| Componente | Precisa existir? | Já existia? | Laravel/PHP/PostgreSQL resolve? | Decisão |
|---|---|---|---|---|
| Project, Application, Environment | sim, agregados do produto | não | Eloquent + ULID | criados |
| `organization_id` em toda a árvore | sim, ADR-0011 e base da FK composta | não | coluna | criado, intencionalmente redundante |
| FK composta | sim, gate do roadmap no banco | não | FK de PostgreSQL | criada em 5 relações |
| Trigger de imutabilidade | sim, FK não protege UPDATE da própria coluna | precedente em `audit_logs` | plpgsql | 1 função, 4 triggers |
| `archived_at` | sim, arquivar sem apagar | `status` enum em membership | timestamp nullable | escolhido por ser binário com data |
| SoftDeletes | não | `User` usa | — | rejeitado: Global Scope esconderia histórico |
| Global Scope / RLS | não | rejeitado no ADR-0011 | — | rejeitados; RLS candidata a P12 |
| Papel `manager` | sim, "quem o owner autoriza" | enum de papéis | enum + CHECK | um case novo |
| Permissões por membership (tabela) | não | — | — | rejeitada: segunda matriz do ADR-0011 |
| Profile global para arquivar | não | perfil `Admin` | — | rejeitado: valeria em todos os tenants |
| 15 permissions | sim, consumidas pelas rotas | catálogo do P1 | enum | criadas |
| Policies (3) | sim, regra contextual | padrão | Gate/Policy | criadas |
| Actions (12) | sim, transação, locks, invariantes | padrão | `DB::transaction`, `sharedLock`, `lockForUpdate` | criadas |
| DTOs (6) | sim, fronteira com payload | padrão | readonly + `validated()` | criados |
| Events (12) | sim, consumidor de auditoria | `Auditable` | event discovery | criados |
| Exceções de lifecycle (3) | sim, 409 com mensagem | padrão do P1 | `render()` | criadas |
| `EnvironmentClassification` | sim, conjunto fechado | não | backed enum + CHECK | criado |
| Status controllers (3) | sim, operações explícitas | `UserStatusController` | controller | criados |
| Customer | não | — | — | não criado (product-model) |
| Tabela de targets/allowlist | não | — | — | adiada para P10 |
| Criador em Application/Environment | não | — | — | não criado: o criador do Project governa a árvore |
| FK simples `applications → organizations` | não | — | composta já implica | não criada |
| Índice para FKs de membership | não | — | — | não criado: memberships nunca são apagadas |
| Pacote de filtros de query | não | — | `when()` | não instalado |
| Service, Repository, classes base | não | — | — | não criados |
| Rotas rasas | não agora | — | viáveis com FK composta | adiadas para P3/P4 |

## Abstraction Review

Três casos concretos (Project, Application, Environment) estão verdes. Pela
regra da Fase 5.2, nada foi abstraído nesta fase.

**1. O que se repetiu** (encontrado no código):

| Repetição | Ocorrências |
|---|---|
| Travar ancestrais com `FOR SHARE`, conferir arquivamento, travar a linha | 12 Actions (1 a 3 níveis) |
| Bloco de `index`: `status`, `search` com escape, ordenação, `paginate(15)`, `meta` | 3 controllers da árvore + 2 do P1 |
| Exceção de lifecycle (`archived`/`notArchived`, `render()` 409) | 3 classes |
| Status controller (archive/restore, `{message, data}`) | 3 classes |
| Policy: reconfirmação da cadeia + `archive` × `archive_own` pelo criador | 3 Policies |
| Quatro eventos com a mesma forma | 12 classes |
| Regra de slug (`regex` + `unique` por pai) | 3 pares de FormRequests + Organization |
| Factory derivando `organization_id` do pai | 2 factories |

**2. O que merece abstração** (na fase seguinte que tocar a árvore):

- **Guarda de escrita da árvore**: um único ponto que trava a cadeia de
  ancestrais na ordem e lança a exceção certa. Reduz 12 cópias e protege a
  regra mais frágil (a ordem dos locks), que hoje só a revisão garante.
- **Filtros de listagem**: scopes locais (`status`, nome com curingas
  escapados) reutilizáveis pelos três models.
- **Regra de slug**: uma rule de validação com o regex e a unicidade por pai.
- **Envelope de paginação**: decidir entre um helper ou o formato padrão de
  `Resource::collection($paginator)` (que muda o contrato: acrescenta `links`).

**3. O que continua específico:**

- Policies: a profundidade da cadeia e a regra do criador diferem por nível;
  mantê-las explícitas favorece auditoria.
- Eventos: uma classe por fato mantém a ação legível e localizável na trilha.
- Exceções e status controllers: a duplicação é pequena e as mensagens fazem
  parte do contrato; generalizar ganharia pouco.
- Regras do responsável e do escopo autorizado: só Environment.

## JUDGE

Tentativas de refutar a hierarquia e as decisões, com evidência:

| Contestação | Evidência | Veredito |
|---|---|---|
| `organization_id` em filhos é redundante | sem ele não existe FK composta; mutação "FK composta → simples" deixa passar filho cross-tenant | mantém |
| Policies bastariam, sem FK composta | SQL direto, tinker e jobs ignoram Policies; `*IntegrityTest` | mantém |
| Scoped binding bastaria, sem reconferir na Policy | mutação sem `scopeBindings()`: a Policy ainda nega (403) | mantém as duas barreiras |
| SoftDeletes seria mais simples | escopo implícito esconderia histórico em jobs e relatórios (ADR-0011) | mantém `archived_at` |
| Customer deveria existir | nenhuma operação compartilhada entre projects exige; product-model | mantém fora |
| Member deveria poder arquivar tudo | arquivar altera o que pode ser avaliado; criador e manager cobrem o fluxo | mantém |
| O responsável deveria poder arquivar | responsabilidade de contato ≠ governança da árvore | mantém; testado |
| Status da application deveria refletir o project arquivado | decisão A: estado próprio; o cliente lê o project | mantém; rever no P13 se o Vue precisar |
| Rotas de quatro níveis não escalam | aceitável até 4; P3/P4 aprofundariam | mantém; revisão registrada no ADR |
| Concorrência não testada | limite do `RefreshDatabase`; protocolo de locks documentado | risco residual aceito, como no P1 |
| Slug de item arquivado não pode ser reutilizado | UNIQUE inclui arquivados: identificador estável para histórico e futuro CLI (P11) | mantém |
| Responsável desativado depois continua referenciado | FK preserva o vínculo; não há aviso | aceito no P2; ver "Riscos residuais" |
| `hasPermission()` repete consultas na mesma requisição | 2 a 4 consultas de membership por escrita | aceito; memoização arrisca estado stale |
| `Application` colide com a classe do Laravel | só `bootstrap/app.php` usa a do framework | mantém o nome; alias documentado |

## Riscos residuais

- Escrita concorrente com arquivamento de ancestral: verificada por revisão,
  sem teste paralelo.
- Responsável de environment com membership desativada continua gravado; o
  P4 deve decidir se um assessment exige responsável ativo.
- Rate limiting nas rotas novas: FIND-016, a resolver no P2.5 antes do P3.
- Superusuário do banco pode remover triggers.

## Findings

- FIND-020: **RESOLVED (parcial)**. Envelope de `store` unificado; continua
  aberta a negação manual em `index`/`show` do IAM.
- FIND-016: **OPEN**; gate do P2.5.
- Nenhum finding novo: a auditoria adversarial do T05 não encontrou defeito
  no código de produção.

## Quality

`composer test`: **628 testes e 2.667 assertions**. `composer lint` e
`composer analyse` verdes. Cada migration do P2 passou por rollback e
reaplicação no banco de teste.
