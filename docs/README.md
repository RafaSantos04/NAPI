# Documentação do NAPI

> Memória arquitetural do projeto. O código é a fonte da verdade: cada afirmação
> aqui aponta para o arquivo, a classe ou o teste que a sustenta. Quando o
> repositório não permite determinar *por que* algo foi feito, isso é dito
> explicitamente.

## Projeto

O NAPI é um projeto de portfólio e laboratório de arquitetura e segurança,
organizado em duas áreas conceituais:

- **IAM (Identity and Access Management)**: usuários, perfis, menus, matriz de
  permissões, autenticação por token e auditoria. Existe como API REST
  (`/api/v1`).
- **Security Lab**: área futura de testes e ferramentas de segurança. Ainda
  não há código; veja [modules/security.md](modules/security.md).

Stack confirmada no repositório: Laravel 13.32, PHP 8.4 (constraint `^8.3`),
PostgreSQL, Sanctum 4, Pest 4, Larastan (nível 6) e Pint (preset `laravel`).

## Arquitetura

- [Visão geral](architecture/overview.md)
- [Ciclo de vida da requisição](architecture/request-lifecycle.md)
- [Estrutura do projeto](architecture/project-structure.md)
- [Architecture Decision Records (ADRs)](architecture/decisions/README.md)

## Módulos

- [Índice de módulos](modules/README.md)
- [IAM](modules/iam.md)
- [Security Lab](modules/security.md)

## Segurança

- [Índice de segurança](security/README.md)
- [Autenticação](security/authentication.md)
- [Autorização](security/authorization.md)
- [Políticas e invariantes de segurança](security/security-policies.md)
- [Auditoria](security/audit.md)
- [Threat model](security/threat-model.md)

## API

- [Índice da API](api/README.md)
- [API v1: inventário de endpoints](api/v1.md)

## Banco de dados

- [Índice do banco](database/README.md)
- [Schema](database/schema.md)

## Testes

- [Estratégia de testes](testing/README.md)
- [Testes de segurança e invariantes](testing/security-tests.md)

## Evolução do projeto

- [Fases](phases/README.md)
- [Fase 3: IAM REST](phases/phase-03-iam-rest.md)
- [Fase 3.2: IAM Security Hardening](phases/phase-03-2-iam-hardening.md)
- [Findings](findings/README.md): problemas e riscos encontrados no Reality
  Check da Fase 3.1, com o status de cada um após a Fase 3.2

## Como manter esta documentação

Toda mudança relevante deve responder, antes do merge:

1. Qual módulo está sendo alterado?
2. Qual política de segurança está envolvida?
3. Existe impacto de segurança? O [threat model](security/threat-model.md)
   precisa mudar?
4. Existe ADR relacionado, ou a mudança é uma decisão arquitetural nova que
   merece um ADR?
5. Os testes cobrem a nova regra? (veja
   [security-tests.md](testing/security-tests.md))
6. Algum [finding](findings/README.md) foi resolvido ou criado?
