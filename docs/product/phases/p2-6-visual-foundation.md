# P2.6 — Visual Foundation / Admin Design System

[← Produto](../README.md) · [Roadmap](../roadmap.md) · [Estado atual](../current-state.md) · [ADR-0008](../../architecture/decisions/ADR-0008-layered-authorization-model.md) · [ADR-0009](../../architecture/decisions/ADR-0009-web-session-and-bearer-adapters.md)

**Status:** em andamento · **trilha paralela ao P3** · **JUDGE:** P2.6-T08

## Objetivo

Estabelecer a identidade visual e a fundação de interface do backoffice Blade
sem transformar os protótipos em requisitos funcionais. O futuro workspace
Vue continua no P13, como cliente separado da API depois do Release Candidate
do backend.

## Contrato visual

A direção oficial é **Cyber Zinc & Emerald Vault**, dark-first:

- superfícies zinc/carbono com bordas de 1px e pouca elevação;
- esmeralda como acento semântico de foco, ação primária e estado protegido;
- sans-serif de sistema para interface e stack monoespaçada para rotas,
  identificadores, hashes e telemetria que exista de fato;
- alta densidade com hierarquia escaneável, sem reduzir texto funcional a
  tamanhos que prejudiquem leitura;
- perigo, aviso, informação e sucesso não dependem apenas de cor.

O guia que recomenda **Cobalt Sentinel** permanece como material exploratório;
ele não é a fonte da implementação. Os protótipos Stitch permanecem em
[`docs/stitch_napi_security_design_system`](../../stitch_napi_security_design_system/)
como referências visuais não executáveis.

## Decisões da fundação

1. **Blade permanece o backoffice.** Ele usa sessão `web`, CSRF, Policies,
   Actions e invariantes. Não chama a própria API por HTTP (ADR-0009).
2. **Vue fica para o P13.** Ele consumirá o contrato congelado da API e poderá
   reutilizar a linguagem visual, mas não markup Blade nem lógica de
   autorização.
3. **CSS direto nesta fase.** O admin continua carregando
   `public/css/admin.css` e renderizando sem manifest Vite. O projeto possui
   Tailwind v4, mas a configuração v3 dos protótipos não será copiada e não
   haverá dependência nova.
4. **Assets locais.** Logo, ícones, fontes ou fallbacks e scripts necessários
   ao runtime não dependem de CDN.
5. **Dados reais.** HSM, FIDO2, MFA, Redis, latência, cobertura, incidentes e
   percentuais de postura só aparecem quando houver fonte real, autorização,
   timestamp e semântica definida.
6. **Navegação é projeção.** Links continuam vindo de `AdminNavigation`; a
   interface nunca concede acesso nem cria atalho ao ADR-0008.

## Primeira entrega

- tokens semânticos Cyber Zinc & Emerald aplicados ao CSS existente;
- marca local em SVG e favicon, sem URL externa;
- header identifica corretamente o adapter como **sessão web**;
- sidebar mantém a lista produzida por `AdminNavigation`;
- landing de login e home administrativa adotam a nova identidade;
- telas existentes herdam campos, botões, tabelas, badges, mensagens e foco
  pelos estilos compartilhados.

## Backlog da fase

| Task | Entrega |
|---|---|
| P2.6-T01 | curadoria das referências e contrato visual |
| P2.6-T02 | pipeline CSS e estratégia de entrega |
| P2.6-T03 | tokens, tipografia, marca e ícones locais |
| P2.6-T04 | componentes Blade e matriz de estados |
| P2.6-T05 | shell e navegação responsiva |
| P2.6-T06 | autenticação, confirmação de senha e tokens |
| P2.6-T07 | IAM e Security Lab |
| P2.6-T08 | acessibilidade, QA visual, documentação e JUDGE |

## Gates

- nenhuma afirmação de segurança ou telemetria ilustrativa em produção;
- nenhum asset remoto necessário ao funcionamento;
- navegação completa por teclado, foco visível e contraste revisado;
- viewports de 360px, 768px e 1280px inspecionados;
- autorização, tenancy e separação entre sessão e Bearer sem regressão;
- `composer test`, `composer lint` e `composer analyse` verdes no JUDGE.
