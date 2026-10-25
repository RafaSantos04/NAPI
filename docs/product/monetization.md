# Monetização

[← Produto](README.md) · [Públicos](target-customers.md) · [Roadmap](roadmap.md)

## Hipótese de receita

O NAPI pode combinar três fontes:

1. assinatura do workspace;
2. Security Review vendido como serviço assistido;
3. plano Agency para software houses entregarem avaliações a clientes.

O serviço assistido permite aprender e faturar antes de existir scanner
abrangente. Billing automatizado não é pré-requisito para pilotos pagos;
faturamento manual é suficiente até o fluxo provar valor.

## Escada de planos a validar

| Plano | Hipótese de público | Diferenciadores candidatos |
|---|---|---|
| Free | developer/portfólio | Security Lab e um projeto limitado |
| Developer | profissional individual | histórico, findings completos e reteste |
| Team | startup/SaaS | membros, mais aplicações, agenda e relatórios |
| Agency | software house | múltiplos projetos/clientes, compartilhamento e white label |
| Business | necessidades avançadas | limites, suporte e controles negociados |

As faixas discutidas — Developer R$ 49–99, Team R$ 249–399 e Agency
R$ 699–1.499 por mês — são somente hipóteses de entrevista. Não devem virar
constantes, limites de banco ou integração de pagamento antes da validação.

## O que medir nos pilotos

- tempo entre criar o projeto e emitir o primeiro relatório;
- número de findings com evidência suficiente sem retrabalho;
- proporção de findings retestados;
- tempo economizado na preparação do relatório;
- frequência de compartilhamento com o cliente;
- disposição a pagar por workspace, review e white label separadamente.

## Gate para P9

Plans & Billing só começa quando ao menos dois pilotos completarem o fluxo
até relatório e houver evidência de compra/renovação. Antes disso, limites de
plano ficam em configuração/documentação, não espalhados em Policies e
controllers.

P9 deve escolher provedor por ADR, manter entitlement separado do status do
pagamento e definir comportamento para atraso, cancelamento, downgrade e
retenção de dados antes de integrar checkout ou webhook.

