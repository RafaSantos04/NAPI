# Produto NAPI

[← Documentação](../README.md) · [Roadmap](roadmap.md) · [Estado atual](current-state.md)

Este diretório registra a trilha **P — Product**: a evolução do NAPI de
projeto de portfólio e laboratório para uma plataforma de validação de
segurança de APIs.

## Estado da definição

A direção do produto e os limites conceituais abaixo estão definidos. Eles
não significam que os respectivos módulos já existam no código.

| Documento | Pergunta respondida |
|---|---|
| [Visão](vision.md) | Qual problema o produto resolve e qual resultado entrega? |
| [Públicos](target-customers.md) | Para quem o produto é construído primeiro? |
| [Estado atual](current-state.md) | O que o repositório já entrega, o que pode evoluir e o que é novo? |
| [Modelo de produto](product-model.md) | Quais são os agregados e relações pretendidos? |
| [Modelo de segurança](security-product-model.md) | Como Lab, assessments, checks e findings permanecem separados? |
| [Monetização](monetization.md) | Quais hipóteses comerciais serão validadas antes de billing? |
| [Roadmap](roadmap.md) | Em que ordem as capacidades serão construídas e quais são os gates? |

## Status do P0

O **baseline documental e arquitetural** do P0 está entregue por estes
documentos. O P0 permanece **em validação** até concluir entrevistas e
pilotos com o público-alvo. Preços, limites de planos, disposição a pagar e
prioridade entre integrações são hipóteses, não decisões de código.

P1 já implementou Organization, Membership e tenancy; veja o
[relatório da fase](phases/p1-organizations-tenancy.md). P2 implementou
Project, Application e Environment com integridade de tenant no PostgreSQL;
veja o [relatório do P2](phases/p2-projects-applications-environments.md).
Os demais agregados comerciais continuam planejados. Antes de cada fase devem ser escritos e
aceitos os ADRs listados no [roadmap](roadmap.md#decisões-arquiteturais-obrigatórias).

## Princípios

1. O primeiro produto vendável organiza uma avaliação de segurança manual ou
   semiautomatizada; ele não promete encontrar automaticamente toda
   vulnerabilidade.
2. O Security Lab continua sintético, educacional e controlado. Dados e
   runners do Lab nunca são reaproveitados como assessment de cliente.
3. Blade continua como adapter administrativo interno. O produto voltado ao
   cliente terá frontend Vue consumindo a API, depois de uma decisão explícita
   sobre autenticação e implantação.
4. A autorização continua seguindo o ADR-0008: permissão funcional → Policy
   contextual → Action/invariante. Tenancy acrescenta contexto; não cria
   atalhos por e-mail, slug ou `is_admin`.
5. Billing vem depois de o fluxo Organization → Application → Assessment →
   Finding → Retest → Report provar valor em pilotos.

