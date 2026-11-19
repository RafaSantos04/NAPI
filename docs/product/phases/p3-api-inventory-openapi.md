# P3 — API Inventory / OpenAPI

[← Produto](../README.md) · [Roadmap](../roadmap.md) · [ADR-0014](../../architecture/decisions/ADR-0014-api-specification-versioning.md) · [ADR-0013](../../architecture/decisions/ADR-0013-abuse-limits-and-expensive-operations.md) · [P2.5](p2-5-platform-security-gate.md)

**Status:** em andamento (T01–T02 concluídos) · **JUDGE:** no P3-T06

## Executive Summary

O P3 importa contratos OpenAPI e projeta deles o inventário de endpoints. O
T01 fixou o modelo e as decisões no
[ADR-0014](../../architecture/decisions/ADR-0014-api-specification-versioning.md):

- **Especificação por Application.** `ApiSpecification` é o contrato lógico
  ("API pública", "API de parceiros"); uma Application pode ter várias.
- **Versão imutável.** Cada import bem-sucedido vira uma
  `ApiSpecificationVersion` que o banco não deixa alterar nem apagar, nem
  por SQL direto. O original fica no PostgreSQL.
- **Sem `ApiImport`.** Com a importação síncrona, não existe estado
  intermediário; a recusa vai para a trilha de auditoria (T02/T04).
- **Rotas rasas.** Listar e criar ficam sob a Application; o resto parte
  da organização.
- **Abstraction Review do P2 aplicada.** A árvore ganhou um ponto único de
  lock de ancestrais, uma regra de slug e scopes de listagem, usados pelos
  três níveis do P2 e pela especificação.

## Escopo entregue

| Task | Entrega | Commits |
|---|---|---|
| T01 | ADR-0014 com as decisões do usuário; `ProjectTreeLock`, `Slug`, `Archivable` e `SearchableByName` (Abstraction Review do P2); tabelas `api_specifications` e `api_specification_versions` (FK composta, trigger de tenant, append-only, CHECKs); 6 permissions `api_specifications.*`; Policy, Actions, requests, resource e rotas da especificação | ver comandos do T01 |
| T02 | `OpenApiParser` para JSON e YAML, contrato `OpenApiDocument`, erro estruturado, validação de OpenAPI 3.0.x/3.1.x, estrutura mínima de `info`/`paths`/operações, referências locais sem rede, ciclos e limites do ADR-0014; `symfony/yaml` declarado como dependência direta | alterações do T02 |

O parser recebe bytes e formato já selecionado pelo `Content-Type`. A rota
HTTP, o limite antes da leitura do corpo, a autorização, a auditoria e a
persistência da versão entram no T04. O T02 não publica endpoint de import.
Os erros carregam motivo estável, JSON pointer e mensagem própria; o texto
original do parser e o conteúdo do cliente não são expostos.

O T02 usa o parser Symfony YAML já instalado, com flags padrão (sem objetos
PHP ou tags customizadas), em vez de adicionar uma biblioteca OpenAPI. A
validação nesta fase cobre o contrato necessário ao inventário; ela não
promete validar todas as extensões semânticas da especificação OpenAPI.
Referências de Path Item locais são resolvidas para projetar operações.
Verificações do T02: 17 testes focados (59 assertions), suíte completa com
791 testes (4.118 assertions), `composer lint`, `composer analyse` e
`git diff --check` aprovados em 19/11/2026.

## Decisões fixadas na execução

| Decisão | Valor | Onde |
|---|---|---|
| Dono da especificação | Application | ADR-0014, item 1 |
| Rotas | criar e listar sob a Application; `show`, `update`, `archive` e `restore` em `/organizations/{organization}/api-specifications/{apiSpecification}` | item 8 |
| Limites de chamada | toda rota com `throttle:authenticated`; import com `api-imports` (30/h por organização e 10/h por usuário, contando toda tentativa) | itens 9 a 11 |
| Teto do documento | 2 MB, depois de autenticação e cota | itens 11 e 13 |
| Swagger 2.0 | fora do P3 | item 14 |
| Recusa de import | auditada como `api_specification_import_rejected`, sem conteúdo; sem coluna de status no `audit_logs` | item 20 |
| Permissões do member | todas menos `archive`; `import` separado de `update` | item 19 |
| `archive_own` | pelo criador do Project, como no P2; a especificação não guarda criador | item 1 |
| Envelope de paginação | mantido (`data` + `meta`): trocar por `Resource::collection($paginator)` acrescenta `links` e muda o contrato de quatro listagens | Abstraction Review do P2 |

## Tests e mutações

| Arquivo | Testes |
|---|---|
| `Feature/ApiInventory/ApiSpecificationTest.php` | 37 |
| `Feature/ApiInventory/ApiSpecificationIntegrityTest.php` | 16 |
| `Feature/Projects/ProjectTreeLockTest.php` | 1 |
| `Feature/Security/ProjectTreeSecurityTest.php` | especificação em todas as regras (auditoria, rollback, isolamento, papel, ids malformados, mass assignment) |
| `Feature/Security/RouteCoverageTest.php` | "never deletes" cobre `api-specifications` |
| `Unit/Domain/Tenancy/Enums/OrganizationRoleTest.php` | +6 linhas na matriz |

Mutações do T01, todas pegas:

| Mutação | Pega por |
|---|---|
| Lock sem conferir Application arquivada | ApiSpecification, Environment e árvore |
| Lock sem conferir Project arquivado | todas as camadas da árvore |
| `Slug` aceitando qualquer valor | requests de Project, Application, Environment e especificação |
| Filtro `archived` ignorado | listagens |
| Busca sem escapar curingas | listagem da especificação |
| `archive_own` sem conferir o criador | 403 do member |
| `view` sem conferir o tenant | Gate sem binding |
| `raw_document` serializado | integridade da versão |
| Member com `api_specifications.archive` | matriz de papéis e 403 |
| Update sem mudança auditado | "does not audit an update that changes nothing" |

## Engineering Gate

| Componente | Precisa existir? | Já existia? | Laravel/PHP/PostgreSQL resolve? | Decisão |
|---|---|---|---|---|
| `ApiSpecification` | sim, contrato lógico com várias versões | não | — | criada |
| `ApiSpecificationVersion` | sim, o que o assessment aponta | não | append-only por trigger | criada |
| `ApiImport` | não com import síncrono | — | — | não criada (ADR-0014, item 4) |
| Arquivo em disco ou S3 | não | — | `text` com TOAST | não criado |
| Função `napi_prevent_row_mutation()` | sim | a dos `audit_logs` tem exceção para `SET NULL` | trigger | criada, genérica pelo `TG_TABLE_NAME` |
| `UNIQUE(organization_id, id)` na versão | não agora | — | — | fica para o T03, junto do `ApiEndpoint` |
| `ProjectTreeLock` | sim, 8 Actions repetiam a ordem de locks (P2) | cópias nas Actions | não | criado; Environment perdeu o parâmetro `$project` |
| `Slug` | sim, 8 requests repetiam a regex | regex inline | `regex:` | rule com a mesma mensagem; unicidade fica em cada request |
| `Archivable`, `SearchableByName` | sim, 3 controllers repetiam os filtros | `when()` inline | scopes locais | traits com scopes |
| Coluna `created_by` na especificação | não | criador do Project | — | não criada |
| Coluna `status` no `audit_logs` | não | resultado no nome da ação e no `meta` | — | não criada; P8 |
| Matriz de permissões configurável pelo owner | não neste P3 | catálogo fixo (ADR-0011) | — | não criada; card a decidir |

## Abstraction Review

**1. O que se repetiu:** a especificação é o quarto nível da árvore. Ela
reaproveitou, sem cópia, o lock de ancestrais, a regra de slug e os scopes
de listagem que a Abstraction Review do P2 pediu. Continuaram repetidos a
exceção de lifecycle, o status controller, os quatro eventos e a Policy.

**2. O que merece abstração:** nada novo. A regra do P2 segue valendo para
exceções, eventos e Policies: as mensagens e a profundidade da cadeia fazem
parte do contrato de cada nível.

**3. O que continua específico:** as rotas rasas, a ausência de criador e a
versão append-only são só da especificação.
