# Modelo de produto

[← Produto](README.md) · [Estado atual](current-state.md) · [Modelo de segurança](security-product-model.md)

Este modelo orienta as próximas fases. Organization e Membership foram
confirmadas e implementadas em P1; Project, Application e Environment, em P2
([ADR-0012](../architecture/decisions/ADR-0012-project-tree-tenant-integrity.md)).
Os demais conceitos continuam conceituais até seus ADRs e fases.

```text
User (identidade global)
  └── Membership ── Organization (tenant)
                        ├── Projects
                        │     └── Applications
                        │           └── Environments
                        │                 ├── API Specification Versions
                        │                 │      └── Endpoint Inventory
                        │                 └── Security Assessments
                        │                        └── Findings
                        │                              ├── Evidence
                        │                              └── Retests
                        └── Subscription (P9)

Security Assessment ── Report snapshots
```

## Responsabilidades

| Conceito | Responsabilidade | Não representa |
|---|---|---|
| Organization | boundary do tenant e dono dos dados comerciais | perfil IAM global |
| Membership | vínculo do usuário com uma organização e seu acesso contextual | nova identidade de login |
| Project | trabalho/engajamento que agrupa aplicações; no início pode representar um cliente da software house | tenant separado |
| Application | sistema lógico avaliado, por exemplo “Billing API” | deployment específico |
| Environment | deployment e escopo autorizado, por exemplo staging ou production | specification version |
| ApiSpecificationVersion | import imutável de um contrato OpenAPI e seu resultado de parsing | estado mutável sobrescrito |
| Endpoint | projeção inventariada de método, path, parâmetros, segurança e respostas | rota do próprio NAPI |
| SecurityAssessment | avaliação autorizada, manual ou assistida, presa a ambiente e versão do contrato | execução do Security Lab |
| SecurityFinding | problema observado num assessment | finding interno de `docs/findings` |
| Evidence | artefato sensível que sustenta um finding ou reteste | log genérico da aplicação |
| Retest | nova verificação de um finding após remediação | edição destrutiva do resultado anterior |
| Report | snapshot publicável de um assessment em um momento | consulta sempre mutável |

## Decisões de modelagem

### Identidade global, dados por organização

`User` continua sendo a identidade autenticável global. O acesso a dados do
cliente nasce de uma `OrganizationMembership` ativa, e toda consulta a um
agregado comercial deve ser limitada à organização da rota. Recursos filhos
devem pertencer ao mesmo tenant por construção e por Policy.

O [ADR-0011](../architecture/decisions/ADR-0011-multi-tenant-organization-context.md)
fixou `owner|member`, `active|disabled` e quatro permissions contextuais. A
entrada funcional continua única: `User::hasPermission()` com Organization,
seguida de Policy e Action. Controllers não decidem por role.

### Project antes de Customer

O MVP não cria uma entidade `Customer`. Para uma software house, `Project`
pode carregar a referência comercial do cliente e agrupar aplicações. Um
agregado Customer só será criado se pilotos demonstrarem operações próprias
compartilhadas entre vários projetos.

### Specifications versionadas

Cada import gera uma versão imutável. Assessments e reports apontam para a
versão usada, preservando a explicação histórica mesmo depois de um novo
upload. O inventário é uma projeção reconstruível do documento importado.

### Histórico não é sobrescrito

Finding muda de workflow, mas evidências e retestes são registros próprios.
O report é snapshot. Essas escolhas preservam a resposta para “o que foi
observado, por quem e com qual contrato?”.

## Estados candidatos, não definitivos

O workflow inicial de finding pode estudar `open`, `confirmed`,
`in_progress`, `resolved`, `accepted_risk` e `false_positive`. A enumeração
só será aceita em P6 depois de observar o fluxo dos pilotos. Assessment e
retest também precisam de máquinas de estado e transições autorizadas, não
apenas strings editáveis.

## Regras mínimas de integridade

- nenhum agregado comercial atravessa organizações;
- assessment referencia exatamente um environment e uma specification
  version compatível com a mesma application;
- finding pertence a um assessment;
- evidência e reteste pertencem ao finding e preservam autoria/data;
- remoção lógica/retention substitui cascade destrutivo onde houver
  evidência ou relatório emitido;
- mudanças relevantes disparam auditoria na mesma transação da Action.

