# Fase 5.2 — Security Lab: Mass Assignment / Property-Level Authorization

[← Fases](README.md) · [Módulo Security Lab](../modules/security.md) · [Mass Assignment](../security/mass-assignment.md) · [ADR-0010](../architecture/decisions/ADR-0010-controlled-security-lab.md#adendo-fase-52) · [Fase 5.1](phase-05-1-security-lab-idor.md)

**Commit:** pending

## Objetivo

Acrescentar ao Security Lab o segundo teste, Mass Assignment, mostrando a
diferença entre aceitar os atributos que o cliente enviou e definir quais
propriedades uma operação pode alterar. O comportamento inseguro fica dentro
do caso de uso, sobre dados sintéticos, e nenhum model real é enfraquecido.

A fase tinha uma segunda metade obrigatória: com dois testes concretos,
comparar os dois e registrar o que se repetiu, o que merece abstração e o
que continua específico, **sem refatorar** ([Abstraction Review](#abstraction-review)).

## Reality check

O que a Fase 5.1 deixou e como foi usado:

| Peça da 5.1 | É genérica ou do IDOR? | Na 5.2 |
|---|---|---|
| Feature flag, middleware `security.lab`, `config/security.php` | genérica | reutilizada sem mudança |
| Permissões `security-lab.view`/`create`, `SecurityTestRunPolicy` | genérica | reutilizada sem mudança |
| `security_test_runs` (colunas, 6 CHECK, índices) | genérica | reutilizada sem mudança de schema |
| Enums `SecurityTestScenario`, `ExecutionStatus`, `ObservedOutcome`, `Verdict` | genéricos | reutilizados; `ObservedOutcome::label()` é vocabulário de IDOR e não foi usado |
| Enum `SecurityTest` | genérico | + `MassAssignment = 'mass_assignment'` |
| Evento `SecurityTestExecuted`, listener `RecordAuditLog` | genéricos | reutilizados sem mudança |
| `SecurityLabResource` (documento sintético) | genérico | reutilizado, + coluna `is_approved` |
| `SecurityLabSeeder`, personas Alice e Bob | genéricos | reutilizados sem mudança |
| `SecurityLabResourcePolicy::view` | do IDOR (posse) | não usada |
| `RunIdorTest`, `RunIdorTestDto`, `RunIdorTestRequest`, `IdorTestController` | do IDOR | intocados, exceto a consulta do resultado no controller ([FIND-025](../findings/README.md#find-025--resultado-da-última-execução-não-era-filtrado-por-teste)) |
| Layout de três colunas, CSS `.lab`, `.choices`, `.segmented`, `.runs`, paginação | genéricos | reutilizados; a marcação foi repetida na nova página |
| Parciais `result` e `outcome` | do IDOR, com nome genérico | renomeadas para `idor-result` e `idor-outcome` |

Outras constatações:

- O IAM real já aplica o controle que o teste demonstra
  ([INV-11](../security/security-policies.md#inv-11--entrada-do-cliente-não-define-atributos-de-autorização)):
  `User` tem `is_active` em `#[Fillable]` e a edição não o altera, porque o
  DTO não o carrega.
- `Model::shouldBeStrict()` está ligado fora de produção: um atributo fora
  do `#[Fillable]` gera exceção. Para a escrita vulnerável ser
  representativa, a propriedade protegida precisa estar na lista do model
  sintético.
- A documentação ainda marcava a 5.1 como `pending`, e `docs/README.md` e
  `overview.md` diziam que o Security Lab não tinha código. Corrigido.

## Engineering Gate

Quatro perguntas por componente: precisa existir, já existe, o framework, a
linguagem ou o banco resolvem, e (nova nesta fase) precisa ser vulnerável de
verdade ou dá para demonstrar no runner controlado.

| Componente | Precisa existir? | Já existia? | Laravel/PHP/PostgreSQL resolvia? | Precisa ser vulnerável de verdade? | Decisão |
|---|---|---|---|---|---|
| Migration | Sim: a propriedade protegida precisa de uma coluna | Não | Schema Builder; `boolean` + default dispensam CHECK | Não se aplica | **Criada** (uma coluna, reversível) |
| Synthetic Resource | Não um novo | `SecurityLabResource` | Eloquent | A lista `#[Fillable]` larga, sim, e só nele | **Reutilizado**, + `is_approved` no `#[Fillable]` e cast `boolean` |
| Entidade `SecurityLabProfile`/`Account` | Não: o documento não tem invariante, ciclo de vida nem semântica incompatíveis | — | — | — | **Não criada** |
| `owner_user_id` como propriedade protegida (sem migration) | Avaliado | Já é fillable | — | — | **Descartado**: é o atributo do IDOR e confundiria objeto com propriedade |
| `SecurityTestRun` / tabela `mass_assignment_tests` | Não | `security_test_runs` | `result_context` em `jsonb` | — | **Reutilizado**, sem mudança de schema |
| Contadores por teste | Não: deriváveis de `security_test_runs` | — | `COUNT` | — | **Não criados** |
| DTO `RunMassAssignmentTestDto` | Sim: fronteira HTTP → caso de uso, com o cenário já convertido e o payload tipado | Padrão existente (ADR-0007) | `readonly` | Não: só transporta o payload | **Criado**, sem `actor` (é o dono do alvo) |
| Action `RunMassAssignmentTest` | Sim: caso de uso real, e único lugar da escrita insegura | Padrão existente | `update()`, `Arr::only`, transação | **Sim, e só aqui**: `update($payload)` sobre o recurso sintético, dentro de transação desfeita | **Criada** |
| Generalizar `RunIdorTest` | Não: leitura × escrita, Policy × contrato, sem estado × rollback | — | — | — | **Não feito** |
| Service / Manager / Runner genérico | Não | — | — | — | **Não criado** |
| Policy nova | Não: quem opera o laboratório já tem Policy; propriedade não é assunto de Policy de objeto | `SecurityTestRunPolicy` | — | — | **Reutilizada**; nenhuma criada |
| `SecurityLabResourcePolicy::update` | Não: o actor é o dono por construção, a checagem nunca negaria | — | — | — | **Não criada** |
| Event `MassAssignmentTestExecuted` | Não | `SecurityTestExecuted` já representa qualquer teste | — | — | **Reutilizado** |
| Listener | Não | `RecordAuditLog` | — | — | **Reutilizado** |
| `afterCommit()` | Não: execução e auditoria continuam na mesma transação, de propósito | — | — | — | **Não introduzido** |
| Enum | Não um novo | Os quatro de resultado servem; `SecurityTest` ganhou um case | Enum nativo | — | **Reutilizados** (+1 case) |
| `MassAssignmentScenario`, `MassAssignmentVerdict` | Não: mesmo conceito | — | — | — | **Não criados** |
| Middleware | Não | `security.lab`, `admin.access`, `permission:` | — | — | **Reutilizados** |
| Feature flag `MASS_ASSIGNMENT_LAB_ENABLED` | Não | `SECURITY_LAB_ENABLED` | — | — | **Não criada** |
| Permissão `security.execute` | Não | `security-lab.create` | — | — | **Não criada** |
| FormRequest `RunMassAssignmentTestRequest` | Sim: valida o experimento e delega à Policy | Padrão existente | `array:chaves`, `accepted`, `Rule::enum`, `exists` | Não: aceita a propriedade protegida como dado do experimento, não como autorização | **Criado** |
| Controller `MassAssignmentTestController` | Sim: página e execução do teste | Não | — | Não: nenhuma rota recebe identificador nem grava payload | **Criado**, fino |
| Rota vulnerável (`PATCH …/vulnerable/…`) | Não | — | — | **Não**: seria uma escrita insegura endereçável | **Não criada** |
| Exception própria | Não | `LogicException` (flag) e o `Throwable` capturado | PHP | — | **Não criada** |
| Reset de estado / snapshot manual | Não | — | **`ROLLBACK` do PostgreSQL** | — | **Não criado** |
| Seeder `MassAssignmentSeeder` | Não | `SecurityLabSeeder` | Default da coluna | — | **Reutilizado sem mudança** |
| Factory | Não uma nova | `SecurityLabResourceFactory` | Default da coluna | — | **Reutilizada sem mudança** |
| Blade: página do teste | Sim | Layout e CSS existentes | Blade | — | **Criada** (`mass-assignment.blade.php`) |
| Blade partial/component: resultado e resumo | Sim: usados na página e no histórico | Padrão do IDOR | `@include` | — | **Criadas** (`mass-assignment-result`, `mass-assignment-outcome`) |
| Componente compartilhado de cenário/histórico | Candidato: marcação idêntica nas duas páginas | — | `@include` | — | **Adiado** para depois da comparação ([Abstraction Review](#abstraction-review)) |
| Editor de JSON livre | Não: aumenta a superfície e piora a leitura | — | Campos explícitos | — | **Não criado** |
| Interface, classe base, registry, pipeline, factory de testes | Não | — | — | — | **Não criados** |
| Repository, API Resource, Trait, Helper | Não | — | Eloquent, Blade | — | **Não criados** |
| ADR novo | Não: a fase segue o ADR-0010 | — | — | — | **Adendo** ao ADR-0010 |

Resultado: **4 classes de aplicação** novas (Action, DTO, FormRequest e
controller), uma migration de uma coluna, um case de enum e três views.

## Arquitetura

```text
Browser → web (sessão, CSRF, EnsureUserIsActive)
        → auth → admin.access → security.lab (flag, 404) → permission:security-lab.* (404)
        → MassAssignmentTestController
             → RunMassAssignmentTestRequest (valida o experimento; authorize() → SecurityTestRunPolicy::create)
             → RunMassAssignmentTestDto (alvo, cenário, payload)
             → RunMassAssignmentTest
                  1. flag ligada?                         (senão LogicException)
                  2. resolve o alvo sintético; actor = dono do alvo
                  3. BEGIN
                       cenário:  vulnerável → update($payload)
                                 protegido  → update(Arr::only($payload, EDITABLE))
                       lê do banco o estado que ficou
                     ROLLBACK                              (sempre)
                  4. resposta observada + veredito, pelo antes × depois
                                                           (erro técnico → inconclusive)
                  5. DB::transaction
                       ├ INSERT security_test_runs
                       └ SecurityTestExecuted → RecordAuditLog → INSERT audit_logs
        → redirect (PRG) → página com o resultado e o histórico
```

### Decisões de desenho

- **Actor = dono do alvo.** O teste é sobre o que alguém já autorizado no
  nível do objeto pode alterar nele. Um actor que não fosse o dono misturaria
  IDOR com Mass Assignment. Por isso o formulário escolhe só o documento, o
  DTO não carrega `actor`, e `acting_as_user_id` é gravado a partir do alvo.
  Operator, actor e alvo continuam três colunas e três conceitos.
- **O contrato fica no caso de uso.** `EDITABLE = ['name']` e
  `PROTECTED = ['is_approved']` são constantes de `RunMassAssignmentTest`. O
  FormRequest valida a forma e deixa a propriedade protegida passar, senão o
  cenário vulnerável viraria protegido antes de começar.
- **Veredito pelo estado, não pelo payload.** O caso de uso relê o documento
  do banco dentro da transação e compara com o estado anterior. Exposição é
  uma propriedade protegida com valor diferente.
- **Restauração por `ROLLBACK`.** Das três estratégias avaliadas (resetar
  antes de cada execução, recurso efêmero, desfazer a escrita), a última é a
  única sem código próprio e sem estado intermediário: o documento nunca
  fica alterado para outra requisição, e a linha de base não precisa ser
  conhecida. A execução e a auditoria são gravadas depois, em outra
  transação, e sobrevivem.
- **Sem `afterCommit()`.** A auditoria continua síncrona e na mesma
  transação da execução, como em todo o NAPI desde a Fase 3.2.

## Banco de dados

| Tabela | Mudança |
|---|---|
| `security_lab_resources` | + `is_approved boolean NOT NULL DEFAULT false` (migration `2026_10_10_100241_add_is_approved_to_security_lab_resources_table`) |
| `security_test_runs` | nenhuma. `test_key = 'mass_assignment'`; `result_context` (jsonb) com `attempted`, `before`, `after`, `protected_changed` |

Sem CHECK, índice ou FK novos: o tipo e o default são toda a integridade da
coluna, e nada consulta por ela. Detalhes em
[schema.md](../database/schema.md#security_lab_resources-fases-51-e-52).

Migration verificada no banco `napi_test` com `migrate:fresh`,
`migrate:rollback --step=1` (coluna removida), `migrate` e
`migrate:fresh --seed`. Para um banco existente:

```bash
php artisan migrate
```

## Cenário vulnerável

| | |
|---|---|
| Operator | quem está autenticado (ex.: `admin@napi.dev`) |
| Target / Actor | Documento A, de Alice |
| Input | `name = "Alice Updated"`, `is_approved = true` |
| Before | `name = "Documento A"`, `is_approved = false` |
| Escrita | `$target->update($payload)` |
| After | `name = "Alice Updated"`, `is_approved = true` (**alterada**) |
| Resultado | `completed` · `allowed` · **EXPOSED** |

## Cenário protegido

| | |
|---|---|
| Target / Actor | os mesmos |
| Input | o mesmo payload |
| Before | `name = "Documento A"`, `is_approved = false` |
| Escrita | `$target->update(Arr::only($payload, ['name']))` |
| After | `name = "Alice Updated"`, `is_approved = false` |
| Resultado | `completed` · `denied` · **PROTECTED** |

Outros casos:

| Caso | Resultado |
|---|---|
| Payload só com `name`, em qualquer cenário | `allowed` · **PROTECTED**, "alteração legítima" |
| Alvo inexistente, ou escrita recusada pelo banco (ex.: UNIQUE de nome) | `error` · **INCONCLUSIVE**, com a execução gravada |

Depois de qualquer execução o documento está como em "Before". Explicação
completa em [mass-assignment.md](../security/mass-assignment.md).

## Fronteiras de segurança

| Camada | Como |
|---|---|
| Feature flag | `SECURITY_LAB_ENABLED` (padrão `false`), a mesma do laboratório inteiro. Conferida no middleware, na navegação, no caso de uso e no seeder |
| Autenticação | sessão `web`; usuário inativo perde a sessão |
| Permissão | `security-lab.view` e `security-lab.create`, pela matriz; nenhum teste por slug de perfil |
| Operator × actor | o actor é o dono do alvo, gravado em coluna própria; nenhum `Auth::login()`; a sessão não muda de dono |
| Dados sintéticos | alvo só `SecurityLabResource` (validação + FK); payload só `name` e `is_approved` (`array:name,is_approved`) |
| Runner controlado | a escrita ampla só existe em `RunMassAssignmentTest`, numa transação sempre desfeita; nenhuma rota recebe identificador nem grava payload |
| Models reais | nenhum `#[Fillable]` ou `$guarded` alterado; nenhum `Model::unguard()` |

## Auditoria

`audit_logs` registra **quem executou**: `security_test_executed`, com o
operator real como ator, a execução como subject e
`meta.test_key = mass_assignment`. `security_test_runs` registra **o que o
teste observou**: cenário, actor, alvo, resposta, veredito e, em
`result_context`, as propriedades enviadas e o estado antes e depois. Uma
execução gera exatamente uma linha em cada tabela, na mesma transação. A
escrita no documento, que é desfeita, não gera auditoria própria.

## Interface

`/admin/security/mass-assignment`, no mesmo layout de três colunas do IDOR:
configuração, resultado e histórico. A configuração tem o documento alvo
(`radio`, com o actor indicado), o payload com as duas propriedades
etiquetadas (`name`, permitida, em campo de texto; `is_approved = true`,
protegida, em `checkbox`) e o cenário em controle segmentado. O resultado
traz o veredito, uma frase e a tabela "Propriedade · Enviada · Antes ·
Depois", com a alteração indevida destacada. Depois de executar, o documento
e o payload ficam preenchidos para rodar o outro cenário. A landing
`/admin/security` lista os dois testes.

**Rever uma execução.** Nas duas páginas (IDOR e Mass Assignment), cada item
do histórico é um link que abre aquela execução no painel "Resultado", com
o mesmo conteúdo de quando ela rodou: a página recarrega com `?run={id}`, o
item fica destacado (`aria-current`) e o resultado traz a linha "Execução de
… · por …". Não há JavaScript nem rota nova. O controller mostra a execução
recém-feita (flash) ou, na falta dela, a escolhida; um `run` que não seja o
id de uma execução **daquele teste** deixa o painel vazio. Basta
`security-lab.view`, a mesma permissão que já mostra o histórico, e nada é
executado. O formulário fica preenchido com a configuração da execução
aberta, pronta para rodar o outro cenário, e a paginação mantém a seleção.

No Mass Assignment a reexibição sai inteira do registro (`result_context`).
No IDOR, veredito e resposta saem do registro, e o conteúdo exposto é lido
do documento sintético atual, porque o conteúdo não é copiado para o
histórico de propósito; se o documento foi excluído, o texto aparece sem o
bloco de conteúdo.

**Checkpoint visual**: servidor real sobre `napi_test` com a flag ligada;
fluxos exercidos por HTTP (landing, execução vulnerável, protegida e
legítima com o mesmo documento, erros de validação, CSRF 419) e conferência
no banco de que o documento continuou `Documento A` / `false` depois de três
execuções reais. Páginas renderizadas no Edge headless a 1868 px, 1280 px e
num iframe de 390 px; a tabela de resultado precisou de ajuste de CSS para
caber em 390 px. Com a flag desligada, as três páginas respondem 404 e o
link some do menu. Não foi feito teste manual num navegador interativo
(foco por teclado).

## Testes

| Arquivo | Testes |
|---|---|
| `tests/Feature/SecurityLab/MassAssignmentTestExecutionTest.php` (novo) | 36 |
| `tests/Feature/SecurityLab/SecurityLabHistoryTest.php` (IDOR) | 7 → 13 (abrir uma execução pelo histórico) |
| `tests/Feature/SecurityLab/SecurityLabAccessTest.php` | 22 → 31 (rotas e casos de uso do novo teste nos mesmos cenários de acesso e flag) |
| `tests/Feature/Security/RouteCoverageTest.php` | mesmos 11; passam a cobrir as rotas novas |

Total da suíte: **315 testes, 1143 asserções** (eram 264 e 935). O que cada
teste garante está em
[security-tests.md](../testing/security-tests.md#security-lab-testsfeaturesecuritylab-fases-51-e-52).

Abrir uma execução pelo histórico foi verificado com mais doze mutações,
seis em cada página, todas detectadas: sem a guarda de tipo do parâmetro;
execução escolhida vencendo a recém-feita; parâmetro ignorado; paginação
perdendo a seleção; todo item marcado como atual; resultado sem filtro por
teste.

No caso de uso, doze mutações do código foram aplicadas e revertidas, todas detectadas:
cenário protegido repassando o payload inteiro; cenário vulnerável filtrando;
flag ignorada pelo caso de uso; veredito invertido; resposta observada
invertida; erro lido como `protected`; operator trocado pelo actor; sessão
entregue ao actor; `commit` no lugar do `rollBack`; histórico sem filtro por
teste; payload aceitando qualquer chave; rota de execução pedindo só `view`.

### Testes antigos alterados

| Teste | Antes | Depois | Motivo |
|---|---|---|---|
| `SecurityLabAccessTest › does not let a view-only user run a test` e `gives the administrator the lab…` | só IDOR | dataset com os dois testes | mesma regra de acesso para todo teste do laboratório |
| `SecurityLabAccessTest › refuses to run the use case when disabled` | só `RunIdorTest` | dataset com os dois casos de uso, e confere que o documento não foi escrito | a flag vale para qualquer caso de uso |
| `RouteCoverageTest › keeps the Security Lab out of the API and of public routes` | procura `security`, `lab`, `idor` | + `assignment` | rota nova |

## Outras mudanças

- Parciais do IDOR renomeadas (`result` → `idor-result`, `outcome` →
  `idor-outcome`): o nome genérico deixou de ser verdadeiro com o segundo
  teste.
- A página IDOR ganhou a mesma abertura de execução pelo histórico, para as
  duas páginas seguirem o mesmo padrão.
- `IdorTestController` passou a consultar histórico e resultado pela mesma
  consulta filtrada por `test_key` (FIND-025).
- `docs/testing/security-tests.md`: o título "Invariante → teste" estava
  colado na primeira linha da tabela; corrigido, e a tabela ganhou as
  invariantes 19 a 24.
- Status da 5.1 com os hashes reais no lugar de `pending`.
- Regra de engenharia: quarta pergunta e as três perguntas de consolidação
  registradas em [project-structure.md](../architecture/project-structure.md#regra-de-engenharia-permanente-desde-a-fase-51).

## Findings

| Finding | Na Fase 5.2 |
|---|---|
| [FIND-024](../findings/README.md#find-024--personas-do-security-lab-são-contas-administráveis) | **Novo, OPEN (deferred)**: personas são contas administráveis em `users`. Dívida já descrita no ADR-0010, agora com impacto analisado |
| [FIND-025](../findings/README.md#find-025--resultado-da-última-execução-não-era-filtrado-por-teste) | **Novo e RESOLVED**: resultado em flash não era filtrado por teste |
| FIND-008, 014, 016, 018, 019 | continuam OPEN, fora do escopo |
| Segurança do teste | sem finding; riscos e mitigações no [threat model](../security/threat-model.md#security-lab) e na INV-24 |

## Abstraction Review

Feita depois de o teste estar implementado e verde, comparando os dois
laboratórios no código. **Nada daqui foi implementado nesta fase.**

### O que se repetiu?

Repetição real, por camada:

| Onde | O que é igual | O que muda |
|---|---|---|
| Casos de uso `RunIdorTest` e `RunMassAssignmentTest` | a guarda da flag (4 linhas); o bloco que grava a execução e dispara `SecurityTestExecuted` na mesma transação (12 linhas); o array de falha (`error` + `inconclusive` + nome da exceção) | só o `test_key` |
| Controllers | `show()`: autoriza, consulta o histórico por `test_key`, pagina por 10, resolve o resultado (flash ou `?run`); `run()`: executa e redireciona (PRG) | `test_key`, relações carregadas, DTO, Action, rota |
| FormRequests | `authorize()`; regras de `target_resource_id` e `scenario`; mensagens de `required`, `ulid`, `exists` e do enum | campos próprios de cada teste |
| Views | moldura da página (cabeçalho, texto, grade `.lab`); o seletor de cenário (15 linhas idênticas); a lista do histórico e o item (link que abre a execução, veredito, hora, resumo, actor → alvo, operator); a linha "Execução de …"; estados vazios; aviso de quem só tem `view`; a moldura do resultado e o parágrafo de inconclusivo | resumo de cada teste, e o "(dono: …)" do IDOR |
| Testes | o trio operator/actor/sessão/auditoria; recusa de alvo de tabela real; cenário fora do conjunto; falha técnica → inconclusivo | o payload e as asserções de resultado |

Já compartilhado, sem duplicação: `security_test_runs` e suas constraints, os
quatro enums de resultado, `SecurityTestExecuted`, `RecordAuditLog`,
`SecurityTestRunPolicy`, flag, middleware, permissões, `AuditContext`, o
CSS, a paginação e os datasets de acesso em `SecurityLabAccessTest`.

### O que merece abstração?

Critério: reduz duplicação relevante, tem semântica estável, tem dois usos
concretos, simplifica a manutenção e não esconde diferença importante.

| Candidato | Evidência | Forma recomendada | Recomendação |
|---|---|---|---|
| Gravar a execução | Bloco idêntico nos dois casos de uso, e é onde vive uma invariante (INV-23: execução e auditoria na mesma transação, operator certo). Errar em um deles é falha de segurança | Um ponto único, sem herança: método estático em `SecurityTestRun` ou função do domínio que recebe teste, cenário, contexto e resultado. O array de falha vai junto | **Extrair na 5.3**, antes do terceiro teste |
| Consulta do histórico e do resultado | Duas cópias, e a divergência entre elas já produziu um defeito (FIND-025) | Local scope do Eloquent em `SecurityTestRun` | **Extrair na 5.3** |
| Seletor de cenário e lista do histórico | Marcação idêntica, com acessibilidade embutida (`fieldset`, `aria`, foco) que não pode divergir | Parciais Blade com `@include`, sem classe de componente | **Extrair na 5.3** |
| Guarda da flag no caso de uso | 4 linhas iguais, mas é a última barreira e ganha em ficar visível no topo de cada Action | — | Manter repetida, ou levar junto com "gravar a execução" se isso não a esconder |
| Regras comuns dos FormRequests | Duas regras e quatro mensagens | Um trait esconderia pouco e acoplaria os formulários | **Esperar** o terceiro caso |
| Controller genérico por `test_key` | Os dois são quase iguais | Exigiria um mapa teste → DTO/Action/view, ou seja, um registry | **Não extrair** |
| Interface / classe base / registry de testes | O miolo dos casos de uso não tem nada em comum | — | **Não extrair** |

Observação no sentido contrário: `SecurityTestObservedOutcome::label()`
devolve "acesso permitido/negado", que é vocabulário de IDOR dentro de um
enum compartilhado. O Mass Assignment não o usa. Na 5.3, mover esse texto
para a parcial do IDOR deixa o enum de fato genérico.

### O que continua específico?

| | IDOR | Mass Assignment |
|---|---|---|
| Pergunta | autorização por objeto (posse) | autorização por propriedade |
| Actor | escolhido, diferente do dono | sempre o dono do alvo |
| Operação | leitura | escrita |
| Controle demonstrado | `Gate::forUser()` + `SecurityLabResourcePolicy` | contrato da operação (`EDITABLE`) |
| Estado | nenhum | escrita em transação desfeita |
| Veredito | pela posse do alvo | pelo estado antes × depois |
| `result_context` | `target_owner_id` | `attempted`, `before`, `after`, `protected_changed` |
| Entrada | actor + alvo + cenário | alvo + cenário + payload |
| Resultado na tela | conteúdo devolvido | tabela de propriedades |

Os dois casos de uso continuam separados. Eles compartilham infraestrutura,
não comportamento, e nada na comparação pede herança.

Uma pergunta fica para o terceiro teste responder: se
`target_resource_id` → `security_lab_resources` serve a um teste cujo alvo
não seja um documento (a ressalva do ADR-0010).

## Critério de aceite arquitetural

| Pergunta | Resposta |
|---|---|
| Por que o atributo estava vulnerável? | Porque a operação entregou ao model tudo o que chegou, e o model aceita `is_approved` em atribuição em massa |
| Quem controlava o payload? | O cliente: no laboratório, o actor (dono do documento), por meio do formulário do operator |
| Qual camada deveria definir os atributos permitidos? | A operação: o contrato de entrada do caso de uso (`validated()`, DTO; no teste, `EDITABLE`) |
| Por que `$fillable` sozinho não é contrato de autorização? | É uma lista por model. Um atributo que uma operação precisa fica aberto a todas as que repassam a entrada crua |
| Onde o comportamento inseguro está isolado? | Num `match` de `RunMassAssignmentTest::write()`, sem rota própria |
| Por que não atinge dados reais? | Alvo só `SecurityLabResource` (validação e FK), payload só com duas propriedades sintéticas, nenhum model real alterado, escrita desfeita |
| Como o mesmo teste demonstra a mitigação? | Mesmo documento, mesmo payload, mesmo `update()`: muda só o que é entregue a ele |
| Como a execução é registrada? | `security_test_runs` (o que foi observado) e `audit_logs` (quem executou), na mesma transação |
| Como sabemos que o operator continua sendo o operator? | Nenhum `Auth::login()`; teste que relê a sessão depois da execução; mutação que entrega a sessão ao actor é detectada |

## Fora do escopo

Extração das abstrações recomendadas; leitura de propriedades em excesso
(a outra metade da API3:2023); recusa explícita com `prohibited`; atributos
aninhados e relações; outros testes (escalação de privilégio, abilities de
token, rate limiting, CSRF); solução para o FIND-024; limpeza do histórico.
