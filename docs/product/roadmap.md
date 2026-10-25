# Roadmap do produto

[← Produto](README.md) · [Estado atual](current-state.md) · [Fases técnicas](../phases/README.md)

O prefixo `P` identifica a nova trilha de produto. As fases técnicas 0–5 não
são renumeradas: elas registram como as fundações foram construídas.

## Leitura executiva

| Fase | Entrega | Estado | Critério de saída principal |
|---|---|---|---|
| 0–3.2 | fundação, IAM REST, reality check e hardening | concluída | autorização em camadas e baseline de segurança |
| 4.1–4.4 | backoffice Blade: shell, usuários, perfis/matriz e menus | concluída | mesmos casos de uso da API, com sessão web |
| 5.1–5.2 | Security Lab: IDOR/BOLA e Mass Assignment | concluída | dois testes sintéticos controlados e auditados |
| 5.3+ | novos Labs | paralelo/futuro | executar somente quando apoiar aprendizado ou findings reais |
| P0 | definição, arquitetura e validação do produto | em validação | documentos entregues + entrevistas/pilotos confirmados |
| P1 | Organizations, Memberships & Tenancy | concluída | isolamento de tenant e autorização contextual testados |
| P2 | Projects, Applications & Environments | planejada | hierarquia comercial e escopo de ambiente auditáveis |
| P3 | Vue SaaS Foundation | planejada | shell do cliente autenticado e tenant-aware, sem duplicar domínio |
| P4 | API Inventory / OpenAPI Import | planejada | import versionado e inventário consultável |
| P5 | Security Assessments | planejada | assessment manual autorizado com workflow e checklist |
| P6 | Findings & Evidence | planejada | finding estruturado e evidência privada com redaction |
| P7 | Remediation & Retest | planejada | ciclo before/after, autoria e decisão rastreáveis |
| P8 | Reports & Sharing | planejada | snapshot reproduzível e compartilhamento revogável |
| P9 | Plans & Billing | planejada, condicionada | pilotos pagos provam valor e entitlement é definido |
| P10 | Automated Security Checks | futuro | checks isolados, seguros, observáveis e autorizados |
| P11 | CI/CD & Integrations | futuro | API/CLI idempotente e feedback útil ao pipeline |
| P12 | Commercial Release & Scale | futuro | operação, suporte, SLOs, retenção e segurança de produção |

## Validação da ordem

O encadeamento é tecnicamente coerente com cinco ajustes obrigatórios:

1. **P0 continua durante P1–P8.** Entrevistas e pilotos não são uma etapa
   descartável; cada fase deve validar a próxima hipótese.
2. **Tenancy vem antes de dados comerciais.** Não se adiciona
   `organization_id` depois que assessments e evidências já existem.
3. **Vue exige decisão de autenticação.** O frontend separado é mantido como
   direção, mas não deve reutilizar personal access tokens no browser por
   conveniência. O ADR de P3 deve decidir cookie first-party, BFF ou outro
   desenho e suas implicações de CORS/CSRF.
4. **Piloto pode ser comercial antes de billing.** P5–P8 permitem serviço
   pago e faturamento manual. P9 automatiza uma proposta já validada.
5. **Automação vem após o workflow manual.** P10 aprende com assessments
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

### P2 — Projects, Applications & Environments

- implementar a hierarquia mínima do [modelo](product-model.md);
- registrar responsável e escopo autorizado do environment;
- não criar Customer enquanto pilotos não provarem a necessidade.

Gate: nenhum recurso pode referenciar pai de outra organização.

### P3 — Vue SaaS Foundation

- projeto Vue 3 + TypeScript + Vite, com Router e estado apenas onde houver
  necessidade comprovada;
- Blade permanece em `/admin`; Vue representa o workspace do cliente;
- API e frontend compartilham contratos, não lógica de autorização.

Gate: autenticação, CSRF/CORS, logout, tenant ativo e erros definidos por ADR
e testes end-to-end mínimos.

### P4 — API Inventory / OpenAPI Import

- começar por upload JSON/YAML; referências remotas ficam desligadas;
- validar tamanho, formato e versão; preservar original e versão imutável;
- projetar endpoints, parâmetros, schemes, bodies, responses e tags;
- Postman e discovery ficam posteriores.

Gate: reimport não altera assessments históricos e arquivos malformados não
expõem parser, filesystem ou rede.

### P5 — Security Assessments

- assessment manual/assistido, checklist e máquina de estados;
- versão da specification, environment, iniciador e datas explícitos;
- registrar autorização e escopo antes de qualquer teste real.

Gate: primeiro piloto conclui um assessment sem runner automatizado.

### P6 — Findings & Evidence

- taxonomia, severidade, endpoint, impacto e recomendação;
- storage privado, redaction, retenção e autorização de evidência;
- referência opcional ao conteúdo do Security Lab.

Gate: um revisor e um desenvolvedor conseguem compreender e agir sobre o
finding sem contexto externo.

### P7 — Remediation & Retest

- histórico de remediação e retestes, sem sobrescrever evidência anterior;
- autoria, data, resultado e before/after;
- estados `accepted_risk` e `false_positive` exigem justificativa e Policy.

Gate: correção pode ser comprovada de ponta a ponta.

### P8 — Reports & Sharing

- relatório como snapshot versionado, com sumário, metodologia, findings,
  evidências selecionadas e retestes;
- compartilhamento expira, pode ser revogado e é auditado;
- white label somente após validar o plano Agency.

Gate: dois pilotos entregam relatório utilizável ao cliente.

### P9–P12

P9 implementa entitlement e billing após o gate comercial. P10 adiciona um
catálogo pequeno de checks automatizados em workers isolados. P11 oferece
CLI/API e integrações somente depois que os resultados forem estáveis. P12
fecha operação comercial: observabilidade, backups/restores testados,
retenção, suporte, SLOs, incident response e capacity planning.

## Decisões arquiteturais obrigatórias

Criar ADRs apenas quando a decisão for tomada, sem registrar como aceita uma
hipótese ainda aberta:

| Antes de | ADR necessário |
|---|---|
| P1 | modelo multi-tenant, contexto de organização e autorização de memberships — ADR-0011 entregue |
| P3 | topologia frontend/backend e autenticação do Vue; relação com ADR-0009 |
| P4 | armazenamento/versionamento do contrato OpenAPI e política para referências externas |
| P6 | classificação, criptografia, retenção e acesso a evidências |
| P8 | snapshots e compartilhamento externo |
| P9 | entitlement, provedor de billing e lifecycle da assinatura |
| P10 | autorização de alvo, egress/SSRF, isolamento de workers e credenciais de checks |

## Qualidade por fase

Cada fase mantém `composer test`, `composer lint` e `composer analyse`,
atualiza os documentos afetados e adiciona testes das negações mais
importantes. Mudanças de tenancy e segurança exigem teste que falhe sem a
regra, seguindo a prática atual do projeto.

