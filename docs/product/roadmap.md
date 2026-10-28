# Roadmap do produto

[← Produto](README.md) · [Estado atual](current-state.md) · [Fases técnicas](../phases/README.md)

O prefixo `P` identifica a nova trilha de produto. As fases técnicas 0–5 não
são renumeradas: elas registram como as fundações foram construídas.

A numeração P segue o quadro do Trello, que é a referência do fluxo de
trabalho; este documento e o quadro devem ter as mesmas fases, com os mesmos
números. A direção é **backend-first**: o frontend do cliente só começa
depois do Release Candidate do backend.

## Leitura executiva

| Fase | Entrega | Estado | Critério de saída principal |
|---|---|---|---|
| 0–3.2 | fundação, IAM REST, reality check e hardening | concluída | autorização em camadas e baseline de segurança |
| 4.1–4.4 | backoffice Blade: shell, usuários, perfis/matriz e menus | concluída | mesmos casos de uso da API, com sessão web |
| 5.1–5.2 | Security Lab: IDOR/BOLA e Mass Assignment | concluída | dois testes sintéticos controlados e auditados |
| 5.3+ | novos Labs | paralelo/futuro | executar somente quando apoiar aprendizado ou findings reais |
| P0 | definição, arquitetura e validação do produto | em validação | documentos entregues + entrevistas/pilotos confirmados |
| P1 | Organizations, Memberships & Tenancy | concluída | isolamento de tenant e autorização contextual testados |
| P1.1 | IAM × Organization Hardening | concluída | IAM, contexto da API e Sanctum consolidados com Organization |
| P2 | Projects, Applications & Environments | concluída | hierarquia comercial e escopo de ambiente auditáveis |
| P2.5 | Platform Security Gate | planejada | rate limiting geral, limites de payload e política de operações caras antes do P3 |
| P3 | API Inventory / OpenAPI | planejada | import versionado e inventário consultável |
| P4 | Assessments | planejada | assessment manual autorizado com workflow e checklist |
| P5 | Findings / Evidence | planejada | finding estruturado e evidência privada com redaction |
| P6 | Retests | planejada | ciclo before/after, autoria e decisão rastreáveis |
| P7 | Reports | planejada | snapshot reproduzível e compartilhamento revogável |
| P8 | Audit / Activity | planejada | auditoria transversal e consultável em todos os domínios |
| P9 | Billing / Plans / Entitlements | planejada, condicionada | pilotos pagos provam valor e entitlement é definido |
| P10 | Automation | futuro | checks isolados, seguros, observáveis e autorizados |
| P11 | Integrations | futuro | API/CLI idempotente e feedback útil ao pipeline |
| P12 | Backend Hardening / Release Candidate | futuro | revisão transversal do backend e contrato de API congelado |
| P13 | Vue SaaS Foundation | futuro | shell do cliente autenticado e tenant-aware, sem duplicar domínio |
| P14 | Commercial Release & Scale | futuro | operação, suporte, SLOs, retenção e segurança de produção |

### Renumeração de 2026-10-28

Até esta data, o roadmap tinha o Vue em P3 e uma fase a mais entre OpenAPI
e Billing. Commits, ADRs e relatórios anteriores podem citar a numeração
antiga; os documentos vivos foram atualizados. Correspondência:

| Antes | Agora | Motivo |
|---|---|---|
| P3 Vue SaaS Foundation | P13 | backend-first: o Vue consome o contrato congelado no P12 |
| P4 API Inventory / OpenAPI | P3 | — |
| P5 Security Assessments | P4 | — |
| P6 Findings & Evidence | P5 | — |
| P7 Remediation & Retest | P6 | — |
| P8 Reports & Sharing | P7 | — |
| — | P8 Audit / Activity | fase do quadro sem correspondente no documento |
| P12 Commercial Release & Scale | P14 | a operação comercial pressupõe o frontend |
| — | P12 Backend Hardening / Release Candidate | fase do quadro sem correspondente no documento |

P1.1, P2.5, P9, P10 e P11 mantiveram o número.

## Validação da ordem

O encadeamento é tecnicamente coerente com seis ajustes obrigatórios:

1. **P0 continua durante P1–P7.** Entrevistas e pilotos não são uma etapa
   descartável; cada fase deve validar a próxima hipótese.
2. **Tenancy vem antes de dados comerciais.** Não se adiciona
   `organization_id` depois que assessments e evidências já existem.
3. **Backend-first.** Os domínios, a auditoria e o contrato de API ficam
   estáveis e revisados (P12) antes do frontend do cliente. Até lá, a API e o
   backoffice Blade são os adapters.
4. **Vue exige decisão de autenticação.** O frontend separado é mantido como
   direção, mas não deve reutilizar personal access tokens no browser por
   conveniência. O ADR do P13 deve decidir cookie first-party, BFF ou outro
   desenho e suas implicações de CORS/CSRF.
5. **Piloto pode ser comercial antes de billing.** P4–P7 permitem serviço
   pago e faturamento manual. P9 automatiza uma proposta já validada.
6. **Automação vem após o workflow manual.** P10 aprende com assessments
   reais e não bloqueia o primeiro produto vendável.

## Escopo e gates por fase

### P1 — Organizations, Memberships & Tenancy

- criar boundary organizacional e memberships;
- decidir organização ativa e autorização contextual sem fonte paralela ao
  ADR-0008;
- garantir isolamento em Policies, queries, rotas, jobs e auditoria;
- definir convite, saída e invariante do último owner/administrador.

Gate: testes negativos de acesso cruzado e ADR aceito. Não inclui Project.

**Entregue:** veja o [relatório P1](phases/p1-organizations-tenancy.md) e o
[ADR-0011](../architecture/decisions/ADR-0011-multi-tenant-organization-context.md).

### P1.1 — IAM × Organization Hardening

- mapear o IAM global contra Organization e Membership;
- consolidar a autorização IAM + Organization numa única entrada
  (`User::hasPermission()` com contexto);
- definir o contexto organizacional da API e revisar os boundaries do Sanctum;
- cobrir tenancy e autorização com Pest.

**Entregue** junto com o P1; não tem relatório próprio, e as decisões estão
no [relatório P1](phases/p1-organizations-tenancy.md) e no ADR-0011.

### P2 — Projects, Applications & Environments

- implementar a hierarquia mínima do [modelo](product-model.md);
- registrar responsável e escopo autorizado do environment;
- não criar Customer enquanto pilotos não provarem a necessidade.

Gate: nenhum recurso pode referenciar pai de outra organização.

**Entregue:** veja o [relatório P2](phases/p2-projects-applications-environments.md) e o
[ADR-0012](../architecture/decisions/ADR-0012-project-tree-tenant-integrity.md).
O gate é garantido por FK composta no PostgreSQL, não só por Policy.

### P2.5 — Platform Security Gate

- rate limiting além do login: por IP no login e limiter padrão por usuário
  nas rotas autenticadas (FIND-016);
- limite de tamanho de corpo e revisão de `max:`/paginação;
- FIND-026 (UTF-8 inválido → 500) e, opcionalmente, FIND-018;
- ADR da política de operações caras (fila, timeout, concorrência por
  organização, tetos de tamanho e de `$ref`, idempotência), aplicada a partir
  do import de OpenAPI; nenhum limiter sem rota consumidora.

Gate: o P3 não começa com o FIND-016 aberto.

### P3 — API Inventory / OpenAPI

- começar por upload JSON/YAML; referências remotas ficam desligadas;
- validar tamanho, formato e versão; preservar original e versão imutável;
- projetar endpoints, parâmetros, schemes, bodies, responses e tags;
- Postman e discovery ficam posteriores.

Gate: reimport não altera assessments históricos e arquivos malformados não
expõem parser, filesystem ou rede.

### P4 — Assessments

- assessment manual/assistido, checklist e máquina de estados;
- versão da specification, environment, iniciador e datas explícitos;
- registrar autorização e escopo antes de qualquer teste real.

Gate: primeiro piloto conclui um assessment sem runner automatizado.

### P5 — Findings / Evidence

- taxonomia, severidade, endpoint, impacto e recomendação;
- storage privado, redaction, retenção e autorização de evidência;
- referência opcional ao conteúdo do Security Lab.

Gate: um revisor e um desenvolvedor conseguem compreender e agir sobre o
finding sem contexto externo.

### P6 — Retests

- histórico de remediação e retestes, sem sobrescrever evidência anterior;
- autoria, data, resultado e before/after;
- estados `accepted_risk` e `false_positive` exigem justificativa e Policy.

Gate: correção pode ser comprovada de ponta a ponta.

### P7 — Reports

- relatório como snapshot versionado, com sumário, metodologia, findings,
  evidências selecionadas e retestes;
- compartilhamento expira, pode ser revogado e é auditado;
- white label somente após validar o plano Agency.

Gate: dois pilotos entregam relatório utilizável ao cliente.

### P8 — Audit / Activity

- revisar o `audit_logs` existente e definir os eventos auditáveis por
  domínio;
- padronizar contexto organizacional e recurso afetado;
- consultas de atividade com autorização por organização e testes de
  integridade.

Gate: toda ação relevante dos domínios P1–P7 é rastreável e consultável sem
expor dados de outra organização.

### P9–P12

P9 implementa entitlement e billing após o gate comercial. P10 adiciona um
catálogo pequeno de checks automatizados em workers isolados, alimentando os
mesmos domínios de Assessment e Finding. P11 oferece CLI/API e integrações
(GitHub, GitLab, CI/CD, webhooks) somente depois que os resultados forem
estáveis. P12 executa a revisão transversal final do backend (IAM, tenancy,
Policies, contratos REST, concorrência, filas, constraints, performance) e
congela o contrato de API do Release Candidate.

### P13 — Vue SaaS Foundation

- projeto Vue 3 + TypeScript + Vite, com Router e estado apenas onde houver
  necessidade comprovada;
- Blade permanece em `/admin`; Vue representa o workspace do cliente;
- API e frontend compartilham contratos, não lógica de autorização.

Gate: autenticação, CSRF/CORS, logout, tenant ativo e erros definidos por ADR
e testes end-to-end mínimos.

### P14 — Commercial Release & Scale

Fecha a operação comercial: observabilidade, backups/restores testados,
retenção, suporte, SLOs, incident response e capacity planning.

## Decisões arquiteturais obrigatórias

Criar ADRs apenas quando a decisão for tomada, sem registrar como aceita uma
hipótese ainda aberta:

| Antes de | ADR necessário |
|---|---|
| P1 | modelo multi-tenant, contexto de organização e autorização de memberships — ADR-0011 entregue |
| P2 | integridade de tenant da árvore de projetos, lifecycle e papéis — ADR-0012 entregue |
| P2.5 | política de abuso e de operações caras (rate limit, payload, fila) |
| P3 | armazenamento/versionamento do contrato OpenAPI e política para referências externas |
| P5 | classificação, criptografia, retenção e acesso a evidências |
| P7 | snapshots e compartilhamento externo |
| P9 | entitlement, provedor de billing e lifecycle da assinatura |
| P10 | autorização de alvo, egress/SSRF, isolamento de workers e credenciais de checks |
| P13 | topologia frontend/backend e autenticação do Vue; relação com ADR-0009 |

## Qualidade por fase

Cada fase mantém `composer test`, `composer lint` e `composer analyse`,
atualiza os documentos afetados e adiciona testes das negações mais
importantes. Mudanças de tenancy e segurança exigem teste que falhe sem a
regra, seguindo a prática atual do projeto.
