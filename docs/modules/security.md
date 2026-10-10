# Módulo Security Lab

[← Módulos](README.md) · [IDOR / BOLA](../security/idor.md) · [ADR-0010](../architecture/decisions/ADR-0010-controlled-security-lab.md) · [Threat model](../security/threat-model.md#security-lab) · [Fase 5.1](../phases/phase-05-1-security-lab-idor.md)

## Estado atual

**Implementado desde a Fase 5.1**, com um teste: [IDOR / BOLA](../security/idor.md).
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
| **Actor** | identidade cuja autorização o teste simula. Nunca assume a sessão | `security_test_runs.acting_as_user_id` |
| **Target** | recurso sintético pedido pelo actor | `security_test_runs.target_resource_id` → `security_lab_resources` |
| **Cenário** | `vulnerable` ou `protected` | `SecurityTestScenario` |
| **Persona** | usuário sintético dono de um recurso do laboratório (Alice, Bob) | `users`, inativo, sem perfil, e-mail `@security-lab.invalid` |

O usuário não é "inseguro" ou "seguro". O que varia é a operação: com ou sem
a verificação de autorização.

### Três fatos por execução

| Fato | Valores | Pergunta |
|---|---|---|
| `execution_status` | `completed`, `error` | o runner terminou? |
| `observed_outcome` | `allowed`, `denied`, ou nulo se houve erro | o que o sistema respondeu? |
| `security_verdict` | `exposed`, `protected`, `inconclusive` | o que isso significa para a segurança? |

Um teste pode terminar bem e provar uma vulnerabilidade (`completed`,
`allowed`, `exposed`). Um erro técnico nunca vira "protegido": é `error` com
veredito `inconclusive`, e o banco recusa qualquer outra combinação.

## Fronteiras

O laboratório contém código deliberadamente inseguro. Cada camada abaixo o
fecha sozinha ([ADR-0010](../architecture/decisions/ADR-0010-controlled-security-lab.md)):

| Camada | Implementação | Quando falta |
|---|---|---|
| Feature flag | `config('security.lab.enabled')`, de `SECURITY_LAB_ENABLED` (padrão `false`): middleware `security.lab`, seção oculta na navegação, recusa em `RunIdorTest` e no `SecurityLabSeeder` | 404 para todos, inclusive `admin` |
| Autenticação | sessão `web` (`auth`) + `EnsureUserIsActive` | redirect para `/admin` |
| Entrada no admin | `admin.access` | sessão encerrada |
| Permissão funcional | `security-lab.view` (laboratório e histórico), `security-lab.create` (executar) | 404 |
| Dados sintéticos | o cenário vulnerável só lê `security_lab_resources`; a FK de `security_test_runs` não aceita outro alvo | — |
| Sem URL vulnerável | não existe rota que devolva um recurso por identificador; a leitura insegura roda dentro de `RunIdorTest` | — |

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
| Escrita | `RecordAuditLog`, pelo evento `SecurityTestExecuted` | `RunIdorTest` |

As duas linhas são gravadas na mesma transação: não existe execução sem
trilha nem trilha sem execução. O log de auditoria aponta para a execução e
não repete o contexto dela.

## Casos de uso

| Caso de uso | Entrada | Autorização | Auditoria |
|---|---|---|---|
| Ver o laboratório e o histórico | — | flag + `security-lab.view` + `SecurityTestRunPolicy::viewAny` | não |
| Executar o teste IDOR | `RunIdorTestRequest` → `RunIdorTestDto` → `RunIdorTest` | flag + `security-lab.create` + `SecurityTestRunPolicy::create` | `security_test_executed` |

## Dados sintéticos

`SecurityLabSeeder` cria Alice e Bob, cada um com um documento cujo conteúdo
começa com `Synthetic Security Lab Data`. Ele não faz nada com o laboratório
desligado e pode ser rodado de novo.

As personas ficam em `users` porque o cenário protegido executa uma Policy
real do Laravel, que precisa de um usuário real. Elas não são contas
utilizáveis: inativas, sem perfil, com senha aleatória que ninguém conhece.
Aparecem na listagem de usuários do admin como inativas.

## Limitações conhecidas

- Um teste só. Não há registro, interface nem classe base de testes:
  a generalização espera o segundo caso concreto.
- Um administrador pode ativar ou excluir uma persona pela área de usuários.
  Excluir remove os documentos dela, e as execuções antigas ficam com o alvo
  nulo e continuam legíveis.
- Não há limpeza do histórico.

## Implementação

- Domínio: `app/Domain/Security/Actions/RunIdorTest.php`, `app/Domain/Security/Enums/*`
- DTO e evento: `app/DTOs/RunIdorTestDto.php`, `app/Events/SecurityTestExecuted.php`
- Models: `app/Models/{SecurityLabResource,SecurityTestRun}.php`
- Policies: `app/Policies/{SecurityLabResourcePolicy,SecurityTestRunPolicy}.php`
- HTTP: `app/Http/Controllers/Web/Admin/Security/IdorTestController.php`, `app/Http/Requests/Web/Admin/Security/RunIdorTestRequest.php`, `app/Http/Middleware/EnsureSecurityLabEnabled.php`
- Configuração: `config/security.php`
- Views: `resources/views/admin/security/*`
- Testes: `tests/Feature/SecurityLab/*`, `tests/Feature/Security/RouteCoverageTest.php`
