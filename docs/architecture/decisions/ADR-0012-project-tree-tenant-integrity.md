# ADR-0012 — Hierarquia Project/Application/Environment com integridade de tenant no PostgreSQL

[← ADRs](README.md) · [Roadmap](../../product/roadmap.md) · [ADR-0011](ADR-0011-multi-tenant-organization-context.md)

## Status

Accepted (P2). Implementado, auditado (T05) e validado pelo JUDGE do T06;
veja o [relatório do P2](../../product/phases/p2-projects-applications-environments.md).

## Context

P2 cria a primeira árvore de dados comerciais sob uma Organization:
Project → Application → Environment. O roadmap exige que nenhum recurso
referencie um pai de outra organização, e o ADR-0011 manda que todo modelo
tenant-aware carregue `organization_id`.

Policies e scoped binding protegem o HTTP, mas não o banco: tinker, seeders,
jobs e SQL direto ignoram as duas camadas. Uma Application com
`organization_id = A` apontando para um Project de B tem de ser impossível no
PostgreSQL, não apenas improvável na API.

Também faltava quem pode arquivar. O par `owner|member` do ADR-0011 obrigava
escolher entre um member somente leitura e um member que arquiva qualquer
coisa. O perfil global `Admin` não pode resolver isso: ele vale para todas as
organizações e não ignora membership (ADR-0011).

## Decision

### Tenant estrutural

1. `projects`, `applications` e `environments` carregam `organization_id`
   `NOT NULL`, mesmo quando o pai já o determina.
2. A referência ao pai é uma FK composta para a chave do tenant:
   `(organization_id, project_id) → projects(organization_id, id)` e
   `(organization_id, application_id) → applications(organization_id, id)`.
   Cada pai declara `UNIQUE(organization_id, id)`, alvo exigido pelo
   PostgreSQL. O `id` continua sendo a PK simples usada pelo Eloquent.
3. Colunas de FK composta são `NOT NULL`. No `MATCH SIMPLE` padrão, uma
   coluna nula desliga a verificação inteira.
4. Referências a memberships usam o mesmo desenho:
   `(organization_id, <coluna>) → organization_memberships(organization_id, id)`.
   Por isso `organization_memberships` ganhou `UNIQUE(organization_id, id)`.
5. A função `napi_prevent_tenant_reassignment()` congela, por trigger
   `BEFORE UPDATE`, as colunas passadas como argumento. A FK composta só
   valida INSERT e troca de pai; o trigger impede mover a linha para outro
   tenant ou outro pai. O primeiro consumidor é `organization_memberships`
   (`organization_id`, `user_id`).
6. A unicidade do slug no filho é `UNIQUE(organization_id, <parent_id>, slug)`,
   que também serve de índice da FK composta (o PostgreSQL não indexa FKs).
7. Sem Global Scope e sem RLS, como no ADR-0011. Escopo continua explícito.

### Lifecycle

8. Os três recursos usam `archived_at` nullable: `active ⇄ archived`. Não
   há SoftDeletes, DELETE pela API nem cascade; FKs são `restrict`.
9. Archive e restore são Actions explícitas e auditadas. Abaixo de um pai
   arquivado nada é criado nem alterado. A Action trava os ancestrais de cima
   para baixo (Project → Application) antes de escrever, serializando com um
   arquivamento concorrente sem risco de deadlock. Ancestrais recebem
   `FOR SHARE` (escritas em filhos diferentes não se bloqueiam); a linha
   alterada recebe `FOR UPDATE`.
10. Arquivar um Project não reescreve os filhos: a árvore fica efetivamente
    inativa pela regra do item 9 e volta inteira no restore.

### Papéis e permissões

11. `OrganizationRole` passa a `owner|manager|member`:
    - owner: tudo;
    - manager: toda a árvore de projetos, inclusive arquivar qualquer item;
      não altera a organização nem gere membros;
    - member: ver, criar e alterar a árvore; arquivar só o que criou.
12. O catálogo `OrganizationPermission` cresce de 4 para 19 keys:
    `{projects,applications,environments}.{view,create,update,archive,archive_own}`.
    `archive` alcança qualquer registro da organização; `archive_own`, só a
    árvore de um Project criado pela membership. Ambas cobrem o restore.
13. `projects.created_by_membership_id` registra o criador: `NOT NULL`,
    FK composta para memberships da mesma organização e imutável pelo
    trigger do item 5. O criador do Project governa o arquivamento da árvore
    inteira; Application e Environment não têm coluna de criador.
14. A regra do criador fica na Policy: `archive`, ou `archive_own` mais
    membership do usuário igual à criadora. Controllers, middleware e
    Policies nunca testam o papel.
15. O manager é atribuído pelo `PATCH /members/{membership}` existente. A
    invariante do último owner continua: rebaixar o último owner para
    manager é 409.
16. O perfil global `Admin` continua restrito ao backoffice e às funções
    globais. Não recebe permissão dentro de tenants.

### API

17. Rotas aninhadas sob `/organizations/{organization}` com
    `scopeBindings()`: `projects/{project}/applications/{application}/environments/{environment}`.
    Archive e restore são `POST .../archive` e `POST .../restore`, como
    `activate`/`deactivate` de usuários. A profundidade será revista quando
    P4/P5 aninharem specifications e assessments sob o environment.
18. `store` passa a devolver `{"data": {...}}` com 201, como `show` e
    `update` (FIND-020). A correção vale para todos os `store` da API v1.

### Environment

19. Environment registra `responsible_membership_id` (`NOT NULL`, FK
    composta), `classification` em enum fechado
    `production|staging|development|other` com CHECK, `authorized_scope`
    obrigatório e `authorization_reference` opcional. O escopo é registro
    humano e auditável; allowlist executável, egress e SSRF ficam para o P10.
    O responsável é opcional no payload (padrão: membership de quem cria),
    precisa estar ativo quando atribuído (validação e revalidação na Action
    sob lock) e pode ser trocado por quem tem `environments.update`. Ser
    responsável não concede arquivamento. O trigger não congela o responsável.

### Nome

20. `Application` é mantido: é o termo da área (OWASP, DAST/SAST, AppSec) e
    do modelo de produto. A classe homônima do Laravel só aparece em
    `bootstrap/app.php`; um arquivo que precise das duas usa
    `use Illuminate\Foundation\Application as LaravelApplication;`.

## Rationale

- A FK composta é declarativa e sempre ativa: protege o banco sem código
  procedural e sem depender de o Eloquent ser usado.
- `organization_id` em cada tabela permite consultas e auditoria por tenant
  sem joins pela cadeia, e é a base do scoped binding.
- O trigger fecha a única brecha da FK composta (UPDATE da própria coluna) e
  reaproveita o precedente do `audit_logs` append-only.
- Um terceiro papel atende “quem o owner autorizar” com um catálogo fechado,
  sem a matriz configurável por tenant que o ADR-0011 adiou.
- `archived_at` representa um estado binário com data, sem esconder linhas
  como o Global Scope de SoftDeletes faria em jobs e relatórios.

## Alternatives Considered

- **Tenant só em `projects`, filhos por join:** descartada; contraria o
  ADR-0011 e não protege a cadeia.
- **`organization_id` em todas as tabelas com FK simples:** descartada;
  permite filho de A apontando para pai de B.
- **Trigger que valida a organização do pai:** descartada como mecanismo
  principal; é procedural e pode ser desabilitada. Usada só para imutabilidade.
- **CHECK ou coluna gerada:** impossível, não referenciam outra tabela.
- **Row Level Security:** protegeria também leituras, mas exige contexto por
  transação, conflita com backoffice, Security Lab, workers e pooling, e é um
  escopo implícito. Candidata a defesa em profundidade no P12.
- **Profile global para arquivar:** descartada; valeria em todos os tenants.
- **Permissões por membership (tabela):** adiada; é a segunda matriz do
  ADR-0011, sem demanda que o papel manager não atenda.
- **Rotas rasas sob a organização:** viáveis com FK composta; adiadas porque
  o aninhamento entrega os ancestrais carregados para a regra de arquivamento.

## Consequences

### Positive

- Nenhum caminho de escrita cria árvore cross-tenant, inclusive SQL direto.
- P4/P5 reutilizam o mesmo desenho (assessment, environment e specification
  da mesma application).
- Member opera a árvore sem poder arquivar o trabalho dos outros.

### Negative

- Cada tabela carrega uma coluna e um UNIQUE aparentemente redundantes.
- Toda nova tabela tenant-aware precisa declarar a FK composta e o trigger.
- Mudar um item de pai exigirá uma decisão nova; hoje é proibido.
- O contrato de `store` mudou para todos os clientes da API v1.

## Security Impact

Fecha IDOR estrutural (filho de outro tenant), reatribuição silenciosa de
tenant e escalada por papel: manager não toca organização nem membros, e
member arquiva só o próprio trabalho. O perfil global continua sem acesso
implícito a tenants.

## References

- [ADR-0008](ADR-0008-layered-authorization-model.md)
- [ADR-0011](ADR-0011-multi-tenant-organization-context.md)
- [Modelo de produto](../../product/product-model.md)
- [FIND-020](../../findings/README.md#find-020--respostas-de-autorização-e-de-tokens-inconsistentes)
