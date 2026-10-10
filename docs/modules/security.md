# Módulo Security Lab

[← Módulos](README.md) · [IDOR / BOLA](../security/idor.md) · [Mass Assignment](../security/mass-assignment.md) · [ADR-0010](../architecture/decisions/ADR-0010-controlled-security-lab.md) · [Threat model](../security/threat-model.md#security-lab) · Fases [5.1](../phases/phase-05-1-security-lab-idor.md) e [5.2](../phases/phase-05-2-security-lab-mass-assignment.md)

## Estado atual

**Implementado desde a Fase 5.1**, com dois testes:

| Teste | `test_key` | Desde | Pergunta |
|---|---|---|---|
| [IDOR / BOLA](../security/idor.md) | `idor` | 5.1 | este objeto é de quem pediu? |
| [Mass Assignment](../security/mass-assignment.md) | `mass_assignment` | 5.2 | esta propriedade pode ser alterada por esta operação? |

Desligado por padrão (`SECURITY_LAB_ENABLED=false`).

Não confundir com a documentação em [`docs/security/`](../security/README.md),
que descreve os **controles de segurança do IAM**. O Security Lab é um módulo
funcional separado, que demonstra esses controles.

## Propósito

Executar, de forma controlada, a versão **vulnerável** e a versão
**protegida** de uma mesma operação, lado a lado, e registrar o que cada uma
permitiu. O objetivo é mostrar o controle funcionando, não oferecer uma
página vulnerável.

## Conceitos

| Conceito | O que é | Onde fica |
|---|---|---|
| **Operator** | usuário real, autenticado, que executa o teste | `security_test_runs.initiated_by_user_id`, e ator do `audit_logs` |
| **Actor** | identidade cuja autorização o teste simula. Nunca assume a sessão. No IDOR é escolhido; no Mass Assignment é sempre o dono do alvo | `security_test_runs.acting_as_user_id` |
| **Target** | recurso sintético que o actor pede (IDOR) ou altera (Mass Assignment) | `security_test_runs.target_resource_id` → `security_lab_resources` |
| **Cenário** | `vulnerable` ou `protected` | `SecurityTestScenario` |
| **Persona** | usuário sintético dono de um recurso do laboratório (Alice, Bob) | `users`, inativo, sem perfil, e-mail `@security-lab.invalid` |

O usuário não é "inseguro" ou "seguro". O que varia é a operação: com ou sem
a verificação de autorização.

### Três fatos por execução

| Fato | Valores | Pergunta |
|---|---|---|
| `execution_status` | `completed`, `error` | o runner terminou? |
| `observed_outcome` | `allowed`, `denied`, ou nulo se houve erro | o sistema atendeu o pedido como foi feito? |
| `security_verdict` | `exposed`, `protected`, `inconclusive` | o que isso significa para a segurança? |

Um teste pode terminar bem e provar uma vulnerabilidade (`completed`,
`allowed`, `exposed`). Um erro técnico nunca vira "protegido": é `error` com
veredito `inconclusive`, e o banco recusa qualquer outra combinação.

Os três fatos valem para os dois testes. `allowed` é "o recurso foi
entregue" no IDOR e "o registro ficou como o payload pediu" no Mass
Assignment; nos dois, `allowed` sem exposição é o uso legítimo (o dono lendo
o que é dele, um payload só com a propriedade permitida).

## Fronteiras

O laboratório contém código deliberadamente inseguro. Cada camada abaixo o
fecha sozinha ([ADR-0010](../architecture/decisions/ADR-0010-controlled-security-lab.md)):

| Camada | Implementação | Quando falta |
|---|---|---|
| Feature flag | `config('security.lab.enabled')`, de `SECURITY_LAB_ENABLED` (padrão `false`): middleware `security.lab`, seção oculta na navegação, recusa em `RunIdorTest`, em `RunMassAssignmentTest` e no `SecurityLabSeeder` | 404 para todos, inclusive `admin` |
| Autenticação | sessão `web` (`auth`) + `EnsureUserIsActive` | redirect para `/admin` |
| Entrada no admin | `admin.access` | sessão encerrada |
| Permissão funcional | `security-lab.view` (laboratório e histórico), `security-lab.create` (executar) | 404 |
| Dados sintéticos | os cenários vulneráveis só leem e escrevem `security_lab_resources`; a FK de `security_test_runs` não aceita outro alvo, e o payload do Mass Assignment só aceita as duas propriedades do teste | — |
| Sem URL vulnerável | não existe rota que devolva um recurso por identificador nem que grave um payload num model; a leitura e a escrita inseguras rodam dentro de `RunIdorTest` e `RunMassAssignmentTest` | — |
| Sem estado residual | a escrita do Mass Assignment roda numa transação sempre desfeita; só a execução e a auditoria são gravadas | — |

Executar um teste cria um registro, então a permissão é a ação `create` da
matriz. Não foi criada uma ação `execute`: a matriz do IAM tem quatro ações
(`view`, `create`, `update`, `delete`) e uma quinta mudaria o modelo de
permissões por causa de um único uso.

## Relação com o IAM

```text
IAM                              Security Lab
─────────────────────────        ─────────────────────────────
autentica e autoriza       ───▶  quem usa o laboratório (sessão, matriz, Policy)
audit_logs                 ◀───  "o operator X executou o teste Y"
Policies (mecanismo)       ───▶  o controle que o cenário protegido demonstra
```

- O laboratório **usa** o IAM, sem mecanismo próprio de acesso.
- O laboratório **não expõe** dados do IAM: usuários reais não são alvo nem
  aparecem como actor.
- `app/Domain/Security` depende de `app/Domain/IAM` (`AuditContext`); o
  contrário não acontece.

## `audit_logs` × `security_test_runs`

| | `audit_logs` | `security_test_runs` |
|---|---|---|
| Responde | quem fez o quê no NAPI | o que aconteceu numa execução de segurança |
| Linha | `security_test_executed`, ator = operator, subject = a execução | cenário, actor, alvo, resposta observada, veredito |
| Escrita | `RecordAuditLog`, pelo evento `SecurityTestExecuted` | `RunIdorTest`, `RunMassAssignmentTest` |

As duas linhas são gravadas na mesma transação: não existe execução sem
trilha nem trilha sem execução. O log de auditoria aponta para a execução e
não repete o contexto dela.

## Casos de uso

| Caso de uso | Entrada | Autorização | Auditoria |
|---|---|---|---|
| Ver o laboratório e o histórico | — | flag + `security-lab.view` + `SecurityTestRunPolicy::viewAny` | não |
| Rever uma execução do histórico | `?run={id}` na página do teste | as mesmas; só execuções daquele teste | não: é leitura, nenhum cenário roda |
| Executar o teste IDOR | `RunIdorTestRequest` → `RunIdorTestDto` → `RunIdorTest` | flag + `security-lab.create` + `SecurityTestRunPolicy::create` | `security_test_executed` |
| Executar o teste Mass Assignment | `RunMassAssignmentTestRequest` → `RunMassAssignmentTestDto` → `RunMassAssignmentTest` | as mesmas | `security_test_executed` |

Os dois testes compartilham permissões, Policy, evento, listener, tabela de
execuções e enums. Cada um tem o seu caso de uso, sem classe base nem
registro: a comparação feita na Fase 5.2 está em
[phase-05-2](../phases/phase-05-2-security-lab-mass-assignment.md#abstraction-review).

## Dados sintéticos

`SecurityLabSeeder` cria Alice e Bob, cada um com um documento cujo conteúdo
começa com `Synthetic Security Lab Data`. Ele não faz nada com o laboratório
desligado e pode ser rodado de novo.

O documento serve aos dois testes. Desde a Fase 5.2 ele tem `is_approved`
(padrão `false`), a propriedade protegida do Mass Assignment. Como a escrita
desse teste é sempre desfeita, os documentos nunca saem do estado do seed.

As personas ficam em `users` porque o cenário protegido executa uma Policy
real do Laravel, que precisa de um usuário real. Elas não são contas
utilizáveis: inativas, sem perfil, com senha aleatória que ninguém conhece.
Aparecem na listagem de usuários do admin como inativas.

## Limitações conhecidas

- Dois testes, cada um com o seu caso de uso, controller, FormRequest e
  página. Há repetição conhecida entre eles (consulta do histórico, seletor
  de cenário, item do histórico), deixada de propósito até a Fase 5.3
  ([Abstraction Review](../phases/phase-05-2-security-lab-mass-assignment.md#abstraction-review)).
- Um administrador pode ativar ou excluir uma persona pela área de usuários
  ([FIND-024](../findings/README.md#find-024--personas-do-security-lab-são-contas-administráveis)).
  Excluir remove os documentos dela, e as execuções antigas ficam com o alvo
  nulo e continuam legíveis.
- Não há limpeza do histórico.

## Implementação

- Domínio: `app/Domain/Security/Actions/{RunIdorTest,RunMassAssignmentTest}.php`, `app/Domain/Security/Enums/*`
- DTOs e evento: `app/DTOs/{RunIdorTestDto,RunMassAssignmentTestDto}.php`, `app/Events/SecurityTestExecuted.php`
- Models: `app/Models/{SecurityLabResource,SecurityTestRun}.php`
- Policies: `app/Policies/{SecurityLabResourcePolicy,SecurityTestRunPolicy}.php`
- HTTP: `app/Http/Controllers/Web/Admin/Security/{IdorTestController,MassAssignmentTestController}.php`, `app/Http/Requests/Web/Admin/Security/{RunIdorTestRequest,RunMassAssignmentTestRequest}.php`, `app/Http/Middleware/EnsureSecurityLabEnabled.php`
- Configuração: `config/security.php`
- Views: `resources/views/admin/security/*`
- Testes: `tests/Feature/SecurityLab/*`, `tests/Feature/Security/RouteCoverageTest.php`
