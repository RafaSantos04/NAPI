# Visão do produto

[← Produto](README.md) · [Públicos](target-customers.md) · [Roadmap](roadmap.md)

## Definição

**NAPI é uma plataforma de validação de segurança de APIs para equipes de
desenvolvimento, SaaS e software houses sem uma equipe dedicada de AppSec.**
Ela ajuda a inventariar APIs, executar avaliações autorizadas, registrar e
explicar problemas, acompanhar correções, comprovar retestes e entregar
relatórios profissionais.

```text
API autorizada
    → inventário/versionamento
    → assessment
    → finding + evidência
    → remediação
    → reteste
    → relatório
```

## Problema

Equipes pequenas frequentemente executam revisões de segurança de forma
manual, tarde no ciclo e com evidências dispersas. Software houses ainda
precisam transformar esse trabalho em uma entrega compreensível e auditável
para seus clientes. O NAPI deve reduzir a distância entre **encontrar**,
**corrigir** e **comprovar**.

## Proposta inicial

O primeiro MVP comercial é o **NAPI Security Review**: um workspace para
organizar e entregar avaliações de segurança de APIs com inventário,
checklist, findings, evidências, recomendações, retestes e relatório. A
execução pode ser manual ou assistida.

O MVP não promete scanner autônomo, cobertura completa nem substituição de
pentest. Automação só entra depois que avaliações reais mostrarem quais
checks são repetíveis, seguros e valiosos.

## Três trilhas do produto

| Trilha | Responsabilidade |
|---|---|
| Platform | organizações, membros, projetos, aplicações, ambientes, IAM e auditoria |
| Security | Security Lab, assessments, findings, evidências, remediação, retestes e checks automatizados |
| Product | frontend SaaS, relatórios, compartilhamento, planos, billing e integrações |

## Diferencial pretendido

Um finding pode apontar para uma explicação prática no Security Lab. O
cliente vê o problema e a correção; o Lab demonstra o mecanismo em dados
sintéticos, sem transformar o alvo real em material educacional e sem
executar código vulnerável contra dados do cliente.

## Fora do escopo inicial

- prometer descoberta completa de APIs por crawler;
- scanner/fuzzer genérico antes de validar o fluxo manual;
- compliance automation completo (LGPD, SOC 2 ou ISO 27001);
- access governance baseado em conectores de terceiros;
- substituir consultoria jurídica, pentest ou AppSec especializado;
- processar um alvo sem autorização e escopo registrados.

## Resultado esperado do MVP

Uma software house deve conseguir cadastrar um trabalho, importar o contrato
da API, conduzir uma revisão, entregar findings com evidências e demonstrar
que cada correção foi retestada. Esse resultado, e não o número de checks
automatizados, é a medida inicial de valor.

