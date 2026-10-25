# Públicos e validação comercial

[← Produto](README.md) · [Visão](vision.md) · [Monetização](monetization.md)

## Público primário: software houses

Equipes que desenvolvem e entregam APIs para vários clientes. O trabalho a
ser resolvido é: **executar uma revisão repetível antes da entrega e gerar
evidência profissional para o cliente**.

Sinais de bom encaixe:

- mantém mais de uma aplicação/API de clientes;
- hoje usa planilhas, documentos e capturas de tela para findings;
- precisa comprovar correções e retestes;
- não tem AppSec dedicado em cada projeto;
- valoriza relatório com identidade própria e compartilhamento controlado.

## Público secundário: startups e SaaS

Equipes de aproximadamente 5–50 desenvolvedores que mantêm APIs próprias,
precisam inserir segurança no ciclo de entrega e ainda não têm uma operação
AppSec madura.

## Usuários do produto

| Papel de uso | Resultado esperado |
|---|---|
| Responsável técnico | enxergar risco, priorizar correções e acompanhar evolução |
| Revisor de segurança | executar checklist, registrar evidência e retestar |
| Desenvolvedor | entender o finding e aplicar a remediação |
| Gestor/cliente | receber sumário e comprovação sem acessar detalhes indevidos |

Esses papéis descrevem necessidades, não autorizam criar checks por string ou
um segundo sistema de permissões. O modelo de autorização organizacional será
decidido em P1 sob o ADR-0008.

## Hipóteses que precisam de evidência

1. O relatório e o histórico de reteste têm valor suficiente mesmo antes da
   automação ampla.
2. Software houses preferem um workspace multi-cliente a documentos soltos.
3. OpenAPI cobre uma parcela suficiente dos primeiros projetos.
4. White label é relevante para o plano Agency, mas não é requisito do
   primeiro piloto.
5. O comprador aceita começar com assessment assistido e checks manuais.

## Critério mínimo de validação do P0

- entrevistar 5 potenciais compradores, priorizando ao menos 3 software
  houses;
- obter 2 pilotos com uma API real autorizada;
- observar o fluxo atual, os artefatos usados e quem aprova a compra;
- validar quais partes geram disposição a pagar: inventário, evidência,
  reteste, relatório, compartilhamento ou automação;
- registrar objeções e alterar o roadmap antes de automatizar.

Contagens são metas de aprendizado, não prova estatística de mercado. Uma
hipótese rejeitada deve mudar o produto, não ser tratada como falha do
entrevistado.

