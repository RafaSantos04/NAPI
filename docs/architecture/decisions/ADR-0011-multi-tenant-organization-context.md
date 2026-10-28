# ADR-0011 — Multi-tenancy por Organization e Membership

[← ADRs](README.md) · [Produto](../../product/README.md) · [Roadmap](../../product/roadmap.md)

## Status

Accepted (P1). Schema, contexto, autorização, isolamento, lifecycle,
invariantes, auditoria e testes foram implementados e validados.

## Context

O NAPI tinha identidades e autorização globais para a API e o backoffice,
mas nenhum boundary de tenant. P2 e as fases seguintes criarão dados
comerciais que pertencem a uma organização. Acrescentar `organization_id`
depois desses agregados existirem deixaria isolamento, auditoria e jobs
dependentes de convenções retroativas.

O ADR-0008 exige uma única entrada para permissão funcional:
`User::hasPermission()` → Policy contextual → Action/invariante. P1 não pode
criar um segundo IAM nem tratar um identificador de organização fornecido
pelo cliente como autorização.

## Decision

### Identidade, tenant e vínculo

1. `User` continua sendo a identidade autenticável global. Login, tokens e
   sessão não recebem organização.
2. `Organization` é o boundary do tenant. Em P1 tem somente identidade,
   nome, slug e timestamps; não há billing, status, exclusão ou soft delete.
3. `Membership` é uma entidade com ULID, `organization_id`, `user_id`, papel
   (`owner` ou `member`), estado (`active` ou `disabled`) e timestamps. O
   banco impede dois vínculos do mesmo usuário na mesma organização.
4. P1 adiciona somente usuários já cadastrados. Convite por e-mail e estado
   `invited` ficam adiados para uma fase que implemente o lifecycle completo.

### Organização corrente

5. A API é stateless: o parâmetro `{organization}` da rota identifica a
   organização solicitada. Trocar de organização significa fazer a próxima
   requisição sob outra rota, não gravar seleção no usuário ou no token.
6. `OrganizationContext` é request-scoped. Um middleware o preenche a partir
   do model binding antes da permissão funcional. O contexto só identifica;
   não concede acesso.
7. O Blade em `/admin` continua backoffice global e não recebe organização
   corrente em P1. O workspace comercial será definido em P13.

### Autorização e isolamento

8. `User::hasPermission(string $permission, ?Organization $organization)`
   continua sendo a única entrada funcional. Sem organização, usa a matriz
   global perfil × menu. Com organização, delega à membership ativa e ao
   catálogo fechado de capacidades do papel.
9. As capacidades mínimas são `organizations.view`,
   `organizations.update`, `members.view` e `members.manage`. Owner recebe
   todas; member recebe apenas leitura da organização e dos membros. O papel
   nunca é testado em controllers ou middleware.
10. Policies recebem o recurso/organização e repetem a decisão contextual.
    A Action protege regras de estado. Model binding localiza; não autoriza.
11. P1 não usa Global Scope. Os modelos globais e o backoffice precisam de
    operações cross-tenant, e um scope implícito esconderia consultas em
    jobs e CLI. Listas partem da relação do usuário; recursos aninhados usam
    scoped binding; middleware e Policies negam acesso cruzado. Os futuros
    modelos tenant-aware deverão carregar `organization_id` e expor scopes
    explícitos reutilizáveis.

### Lifecycle e último owner

12. Remover membership significa mudar seu estado para `disabled`. O
    registro e a auditoria são preservados. Reativação usa a atualização da
    mesma membership.
13. Uma organização sempre possui pelo menos um owner com membership ativa
    e usuário global ativo e não excluído.
14. Toda operação que possa invalidar um owner trava primeiro a linha da
    Organization com `FOR UPDATE` e reconta os outros owners válidos dentro
    da mesma transação. A regra também entra em desativação e exclusão global
    de usuário, porque ambas podem invalidar owners de várias organizações.

### Auditoria e execução futura

15. `audit_logs.organization_id` é nullable: eventos globais e do Security
    Lab continuam sem tenant; eventos de Organization/Membership registram o
    tenant. A FK usa `SET NULL` e o índice favorece histórico por tenant.
16. `audit_logs` passa a rejeitar UPDATE e DELETE no model e no PostgreSQL,
    resolvendo o FIND-014 com enforcement real. Não há hash chaining em P1.
17. Jobs futuros devem carregar `organization_id` explicitamente, resolver a
    Organization no worker e estabelecer um novo `OrganizationContext` para
    a execução. Contexto HTTP nunca é herdado implicitamente por job.

## Rationale

- Rota explícita mantém a API stateless e torna o tenant visível em logs,
  testes e links.
- Membership com estado preserva histórico sem introduzir convite incompleto
  ou deleção destrutiva antes de P2.
- Um catálogo pequeno em código evita uma segunda matriz configurável sem
  impedir futura evolução guiada por casos reais.
- Trancar a Organization cria um ponto de serialização estável inclusive
  quando duas memberships diferentes são alteradas ao mesmo tempo.
- Não usar Global Scope torna queries cross-tenant deliberadas e evita
  contexto invisível em console, auditoria e workers.

## Alternatives Considered

- **Organização em header:** descartada em P1; é menos visível que a rota e
  não ajuda no binding de recursos aninhados.
- **Organização ativa persistida em users ou tokens:** descartada; causa
  conflito entre abas/dispositivos e estado implícito numa API stateless.
- **Sessão para a API:** conflita com o ADR-0009.
- **Nova matriz de roles/permissões por tenant:** adiada até existir demanda
  que o par owner/member não atenda.
- **Global Scope automático:** descartado pelos efeitos em jobs, CLI,
  backoffice e operações cross-tenant.
- **Excluir memberships:** descartado porque apaga lifecycle e dificulta
  auditoria/reentrada.

## Consequences

### Positive

- P2 poderá criar agregados sob Organization sem reinventar contexto.
- Um token ou sessão válidos não bastam para cruzar tenants.
- Remoção de membership tem efeito imediato sem revogar credenciais globais.
- Auditoria distingue fatos globais dos fatos de tenant.

### Negative

- Toda entrada tenant-aware precisa declarar o contexto e usar a Policy.
- Roles fixas não atendem permissões organizacionais customizadas; isso é
  intencional até haver evidência.
- O backoffice ainda não oferece operação visual sobre organizações.

## Security Impact

Mitiga cross-tenant IDOR, adulteração do tenant solicitado, escalação de
membership, organização órfã e contexto stale. Leakage em jobs continua uma
regra arquitetural até haver jobs comerciais para testar; nenhum job de P1
transporta dados de tenant.

## References

- [P1 — Organizations, Memberships & Tenancy](../../product/phases/p1-organizations-tenancy.md)
- [ADR-0008](ADR-0008-layered-authorization-model.md)
- [ADR-0009](ADR-0009-web-session-and-bearer-adapters.md)

