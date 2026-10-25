# Auditoria do estado atual

[← Produto](README.md) · [Modelo de produto](product-model.md) · [Fases existentes](../phases/README.md)

Auditoria inicial realizada em 25/10/2026 contra o commit `6ff5cdd` e
atualizada após a implementação ainda não commitada de P1.

## Baseline verificado

| Evidência | Resultado |
|---|---|
| Rotas próprias | 69 (`php artisan route:list --except-vendor`) |
| Models / migrations / Actions | 11 / 19 / 21 |
| Testes | 424, 1.785 assertions após P1 |
| Formatação | `composer lint` aprovado |
| Análise estática | `composer analyse` aprovado, sem erros |
| Stack instalada | Laravel 13.32, PHP 8.4.27, Sanctum 4.3, PostgreSQL, Pest 4.7 |

## Entregue no código

| Capacidade | Evidência | Situação para o produto |
|---|---|---|
| IAM | `User`, `Profile`, `Menu`, matriz de permissões, Policies, abilities de token e invariantes | fundação reutilizável |
| API REST v1 | autenticação Bearer, CRUD de usuários/perfis/menus, tokens e Resources | adapter reutilizável; contrato ainda não é OpenAPI |
| Admin Blade | sessão web, usuários, perfis, matriz e menus | permanece como backoffice interno |
| Autorização em camadas | ADR-0008 e suíte `Feature/Security` | regra obrigatória para os novos domínios |
| Domínio compartilhado | API e Blade chamam as mesmas Actions/Policies (ADR-0009) | padrão a preservar |
| Auditoria | eventos auditáveis, tenant opcional e `audit_logs` atômicos e append-only | base reutilizável |
| Security Lab | IDOR/BOLA e Mass Assignment, dados sintéticos, flag e histórico | diferencial educacional reutilizável |
| Qualidade | Pest, PostgreSQL real, Pint, Larastan e strict mode | baseline forte para evolução |
| Tenancy P1 | Organization, Membership, contexto por rota, permissions contextuais, invariante de owner | fundação dos agregados comerciais |

As Fases 4.3 e 4.4 estão implementadas. O texto original do novo roadmap as
marcava como planejamento, mas os commits `833e0dd` a `051b2c0`, as rotas,
os controllers e os testes comprovam a entrega.

## Ainda não existe

- projeto, aplicação, ambiente ou cadastro de alvo autorizado;
- importação/versionamento OpenAPI e inventário de endpoints;
- assessment de cliente, finding comercial, evidência ou reteste;
- filas/processamento assíncrono em uso, storage de evidências ou workers
  isolados;
- frontend Vue, dashboard SaaS ou autenticação decidida para esse frontend;
- relatórios, links compartilháveis, planos, limites, billing ou integrações
  CI/CD.

Uma busca pelos conceitos comerciais no código não encontrou implementação.
As ocorrências de “finding” existentes pertencem ao Reality Check interno em
`docs/findings`, não a um domínio de findings de clientes.

## Reutilizar, evoluir, criar, não misturar

| Tratamento | Componentes |
|---|---|
| Reutilizar | identidade global `User`, Actions/DTOs, Policies, eventos/auditoria, PostgreSQL, API versionada, backoffice Blade e práticas de teste |
| Evoluir | rate limits; contrato JSON; filas; storage; observabilidade |
| Criar | Project, Application, Environment, ApiSpecificationVersion, Assessment, Finding, Evidence, Retest, Report e frontend SaaS |
| Não misturar | `SecurityTestRun` com assessment/check real; `SecurityLabResource` com alvo/evidência de cliente; `Profile/Menu` globais com papéis de tenant sem ADR; Blade interno com a experiência do cliente |

## Dívidas que viram gates do produto

Os findings atuais continuam válidos. Antes de exposição pública ou dados de
clientes, o roadmap deve endereçar pelo menos:

- FIND-016: rate limiting além do login;
- FIND-018: sinais de enumeração no login;
- FIND-024: personas do Lab administráveis como usuários comuns;
- FIND-026: bytes UTF-8 inválidos resultam em 500;
- parcelas abertas de FIND-012, 013, 015 e 020, conforme o recurso que as
  acionar.

FIND-008 é bloqueador antes de qualquer funcionalidade que leia, pesquise ou
exiba CPF. O MVP não precisa usar CPF e não deve ampliar essa superfície.

## Conclusão da auditoria

O repositório agora tem um boundary multi-tenant testado e pode seguir para
P2. Ele ainda não é um produto de API security para clientes: não há projeto,
aplicação, assessment nem frontend comercial. Os novos agregados devem nascer
sob Organization e reutilizar o contexto entregue por P1.

