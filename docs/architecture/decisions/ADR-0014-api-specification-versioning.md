# ADR-0014 — Especificações OpenAPI por Application, versões imutáveis e importação sem rede

[← ADRs](README.md) · [Roadmap](../../product/roadmap.md) · [ADR-0012](ADR-0012-project-tree-tenant-integrity.md) · [ADR-0013](ADR-0013-abuse-limits-and-expensive-operations.md)

## Status

Proposed (P3-T01, decisões fechadas com o usuário em 2026-11-01). Passa a
Accepted no JUDGE do P3-T06, como o ADR-0013 no P2.5.

Decisões do usuário:

- a especificação pertence à Application;
- YAML entra, com `symfony/yaml`;
- a importação é síncrona (ADR-0013, item 30);
- rotas rasas a partir da especificação, com limites de chamada próprios
  para o import;
- teto de 2 MB por documento;
- Swagger 2.0 fica fora do P3;
- importações recusadas entram na trilha de auditoria;
- o member recebe todas as permissões da especificação menos `archive`.

## Context

O P3 importa contratos OpenAPI e projeta deles um inventário de endpoints. O
roadmap pede:

- upload de JSON e YAML;
- referências remotas desligadas;
- validação de tamanho, formato e versão;
- original preservado e versão imutável.

O gate do P3 tem duas condições:

- reimportar não altera assessments históricos;
- um arquivo malformado não expõe o parser, o sistema de arquivos nem a
  rede.

O import é a primeira operação cara do sistema. O ADR-0013 (itens 29 a 35)
já definiu a política geral (teto antes do trabalho, execução síncrona
medida, um import por vez em cada organização, cota por organização,
idempotência pelo conteúdo, nenhuma rede, falha sem estado parcial) e deixou
os números para este ADR.

O card P3-T01 pede para separar a fonte lógica da especificação das
execuções concretas de importação. O [modelo de produto](../../product/product-model.md)
punha as versões sob o Environment. O usuário decidiu que pertencem à
**Application**: o mesmo contrato é publicado em staging e produção, e
duplicá-lo por ambiente criaria duas verdades.

## Decision

### Modelo

1. **`ApiSpecification`** é o contrato lógico de uma Application, como "API
   pública" ou "API de parceiros". Uma Application pode ter várias.
   - Campos: `name`, `slug` (único na Application), `description` e
     `archived_at`. Não guarda o criador: o `archive_own` segue o criador
     do Project, que governa a árvore (ADR-0012).
   - Segue a árvore do ADR-0012: `organization_id NOT NULL`, FK composta
     `(organization_id, application_id) → applications(organization_id, id)`,
     `UNIQUE(organization_id, id)` como alvo das FKs filhas, trigger
     `napi_prevent_tenant_reassignment('organization_id', 'application_id')`
     e `archived_at` no lugar de exclusão.
2. **`ApiSpecificationVersion`** é um import bem-sucedido, **imutável**.
   - Campos: `number` (sequencial por especificação), `format`
     (`json|yaml`), `openapi_version`, `info_title`, `info_version`,
     `content_sha256`, `size_bytes`, `raw_document`,
     `imported_by_membership_id` e `created_at`.
   - Não tem `updated_at`.
   - FKs compostas para a especificação e para a membership de quem
     importou.
   - `UNIQUE(api_specification_id, number)` e
     `UNIQUE(api_specification_id, content_sha256)`.
3. **A versão é append-only no banco.** Um trigger `BEFORE UPDATE OR DELETE`
   recusa qualquer alteração, no mesmo padrão do `audit_logs_append_only`.
   A função é própria: a dos `audit_logs` abre uma exceção para o
   `SET NULL` do ator, e aqui não há exceção, porque as FKs da versão são
   `restrict`. Os assessments do P4 apontam para o id da versão, e nenhum
   reimport muda o que eles viram.
4. **Não existe `ApiImport` neste P3.** Com a importação síncrona (ADR-0013,
   item 30), uma tentativa termina em versão criada ou em recusa sem nada
   gravado na especificação; um registro com status (`pending`,
   `processing`, `failed`) só teria consumidor numa fila. Ele entra junto
   com a fila, se a medição a exigir. O histórico é a lista de versões mais
   a trilha de auditoria, que registra também as recusas (decisão 20).
5. **O original fica no PostgreSQL** (`raw_document text`, os bytes
   recebidos, exigidos em UTF-8), não em disco nem em object storage:
   - a gravação é atômica com a versão e com a projeção de endpoints
     (ADR-0013, item 35);
   - herda o tenant pela FK composta e o backup do banco;
   - não existe caminho de arquivo para atravessar.

   O PostgreSQL guarda textos grandes fora da linha (TOAST); revisitar se o
   volume crescer (P12).
6. **O inventário (`ApiEndpoint`, P3-T03) é uma projeção da versão**,
   reconstruível a partir do `raw_document`. Ele pertence à versão, não à
   especificação: cada versão tem o próprio inventário.
7. **Environment não aponta para versão neste P3.** Qual versão está
   publicada em cada ambiente é decisão do P4, onde o assessment liga
   Environment e versão.

### Rotas

8. **Rasas a partir da especificação.** Criar e listar precisam do pai, e
   ficam sob a Application como no P2; o resto parte da organização:

   | Método e caminho (`/api/v1/organizations/{organization}`) | Uso |
   |---|---|
   | `GET`, `POST` `.../projects/{project}/applications/{application}/api-specifications` | listar e criar |
   | `GET`, `PATCH` `/api-specifications/{apiSpecification}` | ver e editar |
   | `POST` `/api-specifications/{apiSpecification}/archive` e `/restore` | arquivar e restaurar |
   | `GET`, `POST` `/api-specifications/{apiSpecification}/versions` | listar versões e importar |
   | `GET` `/api-specifications/{apiSpecification}/versions/{version}` | ver uma versão |

   - Aninhadas até o fim, as versões ficariam no 6º nível de parâmetros. O
     ADR-0012 já previa rotas rasas no P3/P4.
   - O binding continua limitado ao pai imediato (`scopeBindings`, regra do
     `RouteCoverageTest`): a especificação à organização, a versão à
     especificação. A FK composta garante a cadeia no banco, e a Policy a
     reconfere (decisão 19).

### Limites de chamada

9. **Toda rota do P3 herda o `throttle:authenticated`** (120 por minuto por
   usuário, chave única para API e admin; ADR-0013, item 20). O
   `RouteCoverageTest` já exige esse limiter em toda rota autenticada, então
   uma rota nova sem ele quebra a suíte.
10. **O import tem um limiter próprio, `api-imports`, com dois limites**,
    acima dos quais a resposta é 429 com `Retry-After`:
    - **30 por hora por organização**: o teto de custo do tenant (ADR-0013,
      item 32);
    - **10 por hora por usuário**: um membro sozinho não esgota a cota da
      organização inteira.

    O limiter conta **toda tentativa** que passa pela autenticação,
    inclusive as recusadas e as idempotentes. Por isso ele também limita
    quantas recusas entram na auditoria (decisão 20).
11. **Ordem no import:** autenticação, ability do token, os dois limiters,
    e só então o teto de 2 MB. Um anônimo ou alguém acima da cota é
    recusado antes de o servidor ler o corpo grande. O proxy de borda
    precisa aceitar 2 MB nessa rota (`client_max_body_size` ou
    equivalente), o que é nota de deploy.

### Importação

12. **Corpo bruto, sem multipart.** O `POST .../versions` recebe o documento
    como corpo.
    - O `Content-Type` aceito é `application/json` ou `application/yaml`
      (também `application/x-yaml` e `text/yaml`); qualquer outro recebe
      415.
    - O formato vem do `Content-Type`, não da extensão.
    - Isso evita o `upload_max_filesize` do PHP (2 MB no ambiente atual) e
      deixa o `LimitRequestBodySize` medir o corpo exatamente.
    - Serve também à CLI do P11 (`curl --data-binary`).
13. **Primeira exceção ao corpo de 64 KB** (ADR-0013, item 24): **2 MB**.
    - A rota retira o middleware do grupo com `withoutMiddleware()` e o
      aplica de novo com o limite como parâmetro
      (`LimitRequestBodySize:<bytes>`), na posição da decisão 11.
    - O `RouteCoverageTest` passa a aceitar a versão com parâmetro.
14. **Ordem das checagens**, cada uma antes de qualquer trabalho maior (item
    29):
    1. bytes (middleware) e UTF-8;
    2. decodificação com profundidade máxima: `json_decode` com `depth`; no
       YAML, `Yaml::parse()` sem objetos PHP e sem tags customizadas,
       recusando tipo inválido;
    3. campo `openapi` em **3.0.x ou 3.1.x**. Swagger 2.0 recebe uma
       mensagem própria pedindo a conversão; volta ao escopo se algum piloto
       pedir;
    4. `$ref` só local (`#/...`); referência externa (arquivo relativo, URL
       ou URN) é 422 e **nunca é buscada** (item 34);
    5. resolução de `$ref` com teto de expansões e detecção de ciclo;
    6. tetos de domínio: operações, profundidade e expansões (números
       abaixo).
15. **Erros** saem como 422 com uma lista curta de mensagens, cada uma com o
    JSON pointer do problema. Nunca saem stack trace, caminho de arquivo,
    nome de classe do parser ou trecho do documento. A exceção do parser
    vira uma mensagem própria. Isso cumpre a segunda condição do gate.
16. **Execução síncrona** com orçamento de tempo, medido no T04 com amostras
    reais. Se o documento no teto passar do orçamento, entra a fila do
    item 30 e, com ela, o registro de import da decisão 4.
17. **Um import por vez em cada organização.** `Cache::lock()` sem espera,
    com chave pela organização, responde 409 a uma segunda chamada (item
    31). Dentro da transação, a ordem de locks do ADR-0012 ganha um nível:
    Project e Application em `FOR SHARE`, e a especificação em
    `FOR UPDATE`, que serializa a numeração e o arquivamento.
18. **Idempotência pelo conteúdo** (item 33): o mesmo `content_sha256` na
    mesma especificação devolve a versão existente (200), sem versão nova e
    sem auditoria. O mesmo contrato em outro formato é outro conteúdo e gera
    versão nova. Uma transação grava a versão, a projeção de endpoints (T03)
    e o evento de auditoria; qualquer falha desfaz tudo (item 35).

### Números iniciais

São revisados no T04, com medição. Mudar um número exige atualizar este
ADR.

| Teto | Valor | Por quê |
|---|---|---|
| Corpo do import | 2 MB | cobre a maioria dos contratos de produto; um JSON de 2 MB decodificado cabe com folga nos 128 MB de `memory_limit` |
| Profundidade de aninhamento | 64 | `json_decode` e a travessia; contratos reais ficam bem abaixo |
| Operações (método × path) | 2.000 | inventário legível; acima disso, o contrato deveria ser dividido em especificações |
| Expansões de `$ref` | 10.000 | impede a explosão por referências encadeadas |
| Orçamento de tempo | 5 s no teto | síncrono aceitável; acima disso, fila (decisão 16) |
| Imports por organização | 30 por hora | uso humano e de CI folgado, abuso não |
| Imports por usuário | 10 por hora | um membro não esgota a cota da organização |
| Demais chamadas | 120 por minuto por usuário | o `throttle:authenticated` que já existe |

### Autorização e auditoria

19. **Permissions contextuais novas**, no padrão do P2:
    `api_specifications.view`, `.create`, `.update`, `.import`, `.archive` e
    `.archive_own`.
    - Owner e manager têm todas. O member tem todas menos `.archive`, como
      na árvore; o `.archive_own` segue a regra do criador (ADR-0012).
    - `.import` fica separado de `.update` porque importar é a operação
      cara. Hoje o catálogo papel → permissão é fixo em código
      (`OrganizationRole::grants()`, ADR-0011): o owner escolhe o papel de
      cada membro, não a permissão. Com a chave separada, uma matriz
      configurável pelo owner, quando vier, restringe o import sem mexer
      no P3.
    - **Policy** em cadeia: especificação → application → project →
      organization. Ancestral arquivado responde 409; tenant estrangeiro e
      falta de permissão funcional respondem 404, como no P2.
20. **Auditoria.**
    - Eventos de estado: `api_specification_created`, `_updated`,
      `_archived`, `_restored` e `api_specification_version_imported`. O
      import registra número, hash, tamanho e versão OpenAPI.
    - **Recusa:** `api_specification_import_rejected`, para o import
      autenticado e autorizado que o pipeline recusou com 422 (decisão 14).
      O `meta` traz um código estável de motivo (`malformed`,
      `unsupported_version`, `external_ref`, `ref_cycle`, `limit_exceeded`
      etc.), o JSON pointer, o formato, o tamanho e o `content_sha256`, que
      permite correlacionar tentativas repetidas.
    - A recusa é gravada **depois** do rollback, fora da transação da
      versão: a especificação continua intacta e a trilha mostra a
      tentativa.
    - **Sem coluna de status.** O `audit_logs` não tem status. O resultado
      fica no nome da ação, e o detalhe fica no `meta`, como o `verdict` do
      `security_test_executed`. Padronizar o resultado entre os domínios é
      escopo do P8.
    - **Não são auditadas** as respostas anteriores à tentativa: 401, 404,
      413, 415, 429 e o 409 do lock. São controles de protocolo e de abuso,
      e auditá-las deixaria um atacante crescer a trilha. O limiter da
      decisão 10 já limita as recusas auditadas a 10 por usuário e 30 por
      organização por hora.
    - **Nunca** se registra o conteúdo do documento, que é dado
      confidencial do cliente, nem a mensagem crua do parser.

## Rationale

- **Application, não Environment**: o contrato descreve o sistema lógico;
  ambientes são publicações dele. Assessments (P4) ligam as duas pontas
  sem duplicar contrato.
- **Imutável e append-only no banco**: a resposta "qual contrato foi
  avaliado?" não pode depender de nenhum código lembrar de não alterar uma
  linha (mesma lógica dos `audit_logs`).
- **Sem `ApiImport` agora**: o registro de import só tem consumidor numa
  fila (regra de engenharia do projeto). A recusa já tem lugar na trilha.
- **Banco em vez de disco**: atomicidade, tenant e ausência de caminho de
  arquivo valem mais que economizar espaço no início.
- **Corpo bruto em vez de multipart**: um limite só, medido pelo middleware
  que já existe, e a mesma rota serve à API, à CLI e à CI.
- **Dois limites no import**: o da organização protege o custo; o do
  usuário impede que um membro tire o import dos outros.

## Alternatives Considered

| Alternativa | Por que não |
|---|---|
| Especificação sob Environment | duplica o contrato por ambiente; decisão do usuário |
| Rotas aninhadas até a versão | seis níveis de parâmetros sem ganho de isolamento; a FK composta e a Policy já garantem a cadeia |
| `ApiImport` com status desde já | sem fila não há estado intermediário; a recusa vai para a trilha |
| Coluna `status` no `audit_logs` | um consumidor só; o padrão atual põe o resultado na ação e no `meta`; o P8 decide se padroniza |
| Auditar 413, 415, 429 e 409 | controles de abuso que um atacante usaria para inflar a trilha |
| Só cota por organização | um membro esgota a cota de todos |
| Arquivo em disco ou S3 | não é transacional com a versão; abre caminho de arquivo; candidato do P12 se o volume pedir |
| Upload multipart | depende do `upload_max_filesize`, e o corpo em chunks não é medido |
| Fila desde o início | o ADR-0013 manda medir primeiro |
| Biblioteca OpenAPI completa | várias resolvem `$ref` remoto e trazem dependências; a escolha entre normalizador próprio e biblioteca fica para o T02, com a restrição de nenhuma rede |
| Aceitar Swagger 2.0 | dobra o normalizador; volta se pilotos pedirem |

## Consequences

### Positive

- Assessments futuros apontam para uma versão que o banco não deixa mudar.
- Reimportar o mesmo arquivo não polui o histórico.
- Recusas ficam rastreáveis por organização sem guardar o documento.
- O import cabe no gate do P2.5: teto, tenant, lock, cota e nenhuma rede.

### Negative

- O documento ocupa espaço no PostgreSQL e no backup.
- Uma recusa ocupa uma linha da trilha (limitada pela cota).
- O owner ainda não escolhe permissões individuais do member; a matriz
  configurável segue adiada pelo ADR-0011.
- O modelo de produto muda: versões passam de Environment para
  Application. O documento é atualizado quando o ADR for aceito.
- `symfony/yaml` vira dependência direta. Hoje ela já está instalada como
  dependência transitiva (v8.1.8); o `require` entra no T02, com
  aprovação do usuário de 2026-11-01.

## Security Impact

Testes obrigatórios ao longo do P3, exigidos pelo JUDGE do P3-T06:

- corpo acima do teto → 413; abaixo dele, sem `Content-Type` aceito → 415;
- anônimo e acima da cota → 401 e 429 sem leitura do corpo grande;
- a cota por usuário e a por organização valem cada uma sozinha, e tentativas
  recusadas consomem cota;
- JSON e YAML malformados, não UTF-8, profundidade excessiva, Swagger 2.0 →
  422 sem detalhe interno;
- `$ref` externo (relativo, `http`, `file`) → 422 e **nenhuma** tentativa de
  rede ou de leitura de arquivo, provada com `Http::preventStrayRequests()`
  e sem acesso a disco;
- YAML com aliases encadeados ("billion laughs") recusado dentro do
  orçamento de tempo e de memória. **Risco a medir no T02**: o
  `symfony/yaml` resolve aliases, e a travessia posterior pode explodir; as
  mitigações candidatas são o teto de nós durante a travessia e a recusa de
  âncoras;
- ciclo de `$ref` e expansão acima do teto → 422;
- mesmo conteúdo duas vezes → uma versão só; dois imports simultâneos na
  organização → 409;
- versão não muda nem é apagada nem por SQL direto (trigger);
- isolamento: especificação e versão de outra organização → 404; FK
  composta recusa um pai de outro tenant;
- recusa gera um `api_specification_import_rejected` com motivo e hash, sem
  conteúdo nem mensagem do parser, e sem versão gravada;
- auditoria sem o conteúdo do documento.

## References

- ADR-0011 (tenant explícito e catálogo fixo de papéis), ADR-0012 (árvore e
  FK composta), ADR-0013 (operações caras, itens 20, 24 e 29 a 35)
- [Roadmap, P3](../../product/roadmap.md)
- [Modelo de produto](../../product/product-model.md)
