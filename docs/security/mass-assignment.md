# Mass Assignment / autorização em nível de propriedade

[← Segurança](README.md) · [Módulo Security Lab](../modules/security.md) · [IDOR / BOLA](idor.md) · [ADR-0010](../architecture/decisions/ADR-0010-controlled-security-lab.md) · [Fase 5.2](../phases/phase-05-2-security-lab-mass-assignment.md)

## O que é

**Mass assignment** é atribuir a um objeto, de uma vez, os atributos que o
cliente enviou. Vira falha quando a operação aceita mais atributos do que
pretendia: o cliente descobre o nome de uma propriedade e a envia junto com
os campos legítimos.

```text
PATCH /documentos/{id}      { "name": "Novo nome", "is_approved": true }

  payload  →  $documento->update($payload)  →  as duas propriedades mudam
                        ▲
        faltou: "esta operação pode alterar esta propriedade?"
```

O atacante não quebra a autenticação e, diferente do IDOR, nem precisa de um
objeto alheio: ele altera um objeto **que é dele**, numa propriedade que não
cabe a ele mudar. São duas lições:

- **existir no model não é poder alterar**: a coluna existe e aceita
  atribuição, mas isso não diz quem pode mudá-la nem em qual operação;
- **válido não é autorizado**: `is_approved = true` é um booleano perfeito.
  A validação de formato passa, e a propriedade continua não sendo da
  operação.

## Diferença para IDOR

| | [IDOR / BOLA](idor.md) | Mass Assignment |
|---|---|---|
| Pergunta que faltou | este **objeto** é de quem pediu? | esta **propriedade** pode ser alterada por esta operação? |
| O que o cliente manipula | o identificador | o conteúdo do payload |
| Objeto atingido | de outra pessoa | o próprio, legitimamente acessível |
| Controle | Policy de posse | contrato de entrada da operação |
| Efeito | leitura indevida | escrita indevida |

No laboratório os dois testes usam o mesmo documento sintético, justamente
para isolar essa diferença: no teste de Mass Assignment o actor é sempre o
**dono** do documento, de modo que a autorização por objeto está resolvida e
só resta observar a de propriedade.

## Enquadramento OWASP

O nome muda conforme a lista e a edição, e as categorias não são
equivalentes entre si:

| Lista | Categoria | Observação |
|---|---|---|
| OWASP API Security Top 10 **2019** | API6:2019 Mass Assignment | categoria própria |
| OWASP API Security Top 10 **2023** | API3:2023 Broken Object Property Level Authorization | une Mass Assignment (escrita) e Excessive Data Exposure (leitura, API3:2019) |
| OWASP Top 10 **2021** (web) | A08:2021 Software and Data Integrity Failures | é onde o CWE-915 está mapeado, não em A01 |
| CWE | CWE-915, Improperly Controlled Modification of Dynamically-Determined Object Attributes | identificador estável da falha |

Conceitualmente a falha é de **controle de acesso** (a aplicação deixou
alguém alterar o que não podia), e é assim que a lista de APIs de 2023 a
trata. Mas o Top 10 web de 2021 não a põe em A01 Broken Access Control, e
este documento não força esse número. O laboratório cobre só a metade de
escrita da API3:2023; a de leitura (devolver propriedades demais) não é
demonstrada. Antes de citar um número num relatório, confira a edição
vigente da lista.

## O cenário do laboratório

```text
Operator  admin@napi.dev          quem executa o teste (sessão real)
   ↓
Target    Documento A             recurso sintético
   ↓
Actor     Alice, dona do alvo     de quem é o payload
   ↓
Payload   name = "Alice Updated"  permitida: renomear é a operação
          is_approved = true      protegida: aprovar cabe a uma revisão
```

A operação testada é **"o dono renomeia o próprio documento"**. O contrato
dela tem uma propriedade, `name`. `is_approved` é uma propriedade do mesmo
documento que o dono conhece, mas que pertence a outra operação (uma
revisão, que o laboratório não modela).

| | Vulnerável | Protegido |
|---|---|---|
| Entrada | o payload inteiro | o mesmo payload |
| Escrita | `$target->update($payload)` | `$target->update(Arr::only($payload, ['name']))` |
| `name` | alterada | alterada |
| `is_approved` | **alterada** | ignorada |
| Resposta observada | `allowed` | `denied` |
| Veredito | **EXPOSED** | **PROTECTED** |

A diferença entre os dois cenários é o que é entregue ao mesmo `update()`.
A relação entre o payload e o estado alterado é real: nada é atribuído que o
payload não tenha trazido, e desmarcar a propriedade protegida no formulário
muda o resultado.

### Propriedade permitida × protegida

O formulário mostra as duas com a etiqueta do contrato (`permitida`,
`protegida`). Não há campo de JSON livre: o payload só aceita `name` e
`is_approved`, validados por `RunMassAssignmentTestRequest`
(`array:name,is_approved`). Qualquer outra chave, inclusive `owner_user_id`,
é recusada antes de chegar ao caso de uso.

### A operação legítima

Enviar só `name` é a operação funcionando. O veredito é **PROTECTED** nos
dois cenários e a interface mostra "alteração legítima". A mitigação não
bloqueia o que a operação deve fazer.

| Payload | Cenário | Resposta | Veredito |
|---|---|---|---|
| `name` + `is_approved` | vulnerável | allowed | **exposed** |
| `name` + `is_approved` | protegido | denied | **protected** |
| só `name` | qualquer | allowed | **protected** (alteração legítima) |
| — | falha técnica | — | **inconclusive** |

`allowed` significa que o registro ficou exatamente como o payload pediu;
`denied`, que algo enviado não foi aplicado. O veredito sai do **estado
observado** (antes e depois, lidos do banco), não do payload: só é exposição
quando uma propriedade protegida mudou de valor.

## O papel de `$fillable`

`SecurityLabResource` declara `is_approved` em `#[Fillable]`, de propósito e
só nesse model sintético. É a premissa do teste, e é o que acontece em
sistemas reais: a lista cresce porque **alguma** operação precisou do
atributo (uma aprovação, um seeder, uma factory), e a partir daí toda
operação que repassa a entrada crua o herda.

`$fillable` responde "quais atributos este **model** aceita em atribuição em
massa?". A pergunta de segurança é outra: "quais atributos **esta operação**
pode alterar?". As duas listas não coincidem, e uma lista por model não pode
representar várias operações com contratos diferentes. Nos dois cenários do
laboratório o `$fillable` é o mesmo, e só um deles expõe a propriedade.

`$fillable` continua sendo uma camada útil: barra o que nenhuma operação
deveria atribuir em massa. Com `Model::shouldBeStrict()` (ligado no NAPI
fora de produção), um atributo fora da lista gera exceção em vez de ser
descartado em silêncio. Mas não é contrato de autorização.

## O papel do FormRequest

O FormRequest tem duas funções diferentes, e só uma delas é a defesa:

1. **Validar a forma**: tipo, tamanho, existência. Aqui `is_approved` passa.
2. **Definir o contrato da operação**: `validated()` devolve só as chaves
   que têm regra. Persistir `$request->validated()` em vez de
   `$request->all()` é a mitigação idiomática do Laravel, e uma regra
   `prohibited` torna a recusa explícita.

No laboratório o FormRequest **precisa** deixar a propriedade protegida
passar, senão o cenário vulnerável viraria protegido antes de começar. Ele
valida o experimento (alvo sintético, cenário, as duas propriedades
conhecidas), e a fronteira da operação fica visível dentro do caso de uso,
onde os dois cenários podem ser comparados:

```php
// app/Domain/Security/Actions/RunMassAssignmentTest.php
public const EDITABLE = ['name'];          // contrato de "renomear"
public const PROTECTED = ['is_approved'];  // conhecida, mas de outra operação

$target->update(match ($dto->scenario) {
    SecurityTestScenario::Vulnerable => $dto->payload,
    SecurityTestScenario::Protected => Arr::only($dto->payload, self::EDITABLE),
});
```

Isso não é licença para `$request->all()` em código de aplicação. O
`Arr::only` do cenário protegido faz o papel que `validated()` faz numa
operação real.

### O mesmo controle no IAM real

O NAPI já aplica essa defesa fora do laboratório
([INV-11](security-policies.md#inv-11--entrada-do-cliente-não-define-atributos-de-autorização)):
`User` tem `is_active` em `#[Fillable]`, porque `ActivateUser` e
`DeactivateUser` o usam, e mesmo assim a edição de usuário não consegue
alterá-lo, porque `UpdateUserDto` só carrega nome, e-mail e senha. É o mesmo
desenho: lista do model larga, contrato da operação estreito.

## Mitigação

- Persistir só o contrato da operação: `validated()`, `safe()->only([...])`
  ou um DTO com os campos da operação.
- Uma operação por intenção: renomear, aprovar e transferir são casos de uso
  diferentes, com autorização diferente, em vez de um `update` genérico.
- `$fillable` enxuto e `Model::shouldBeStrict()` como rede de proteção.
- Nunca `$guarded = []`, `forceFill($request->all())` ou `Model::unguard()`
  em caminho que recebe entrada do cliente.
- Teste que envia a propriedade protegida e confere que ela não mudou.

## Por que dados sintéticos

O cenário vulnerável **escreve** de verdade. Se o alvo fosse `users` ou
`profiles`, o laboratório seria uma escalação de privilégio real: bastaria
enviar `is_active` ou um perfil. Por isso o alvo é sempre um
`SecurityLabResource`, a propriedade protegida é sintética (aprovar um
documento fictício não concede nada no NAPI) e nenhum model real teve
`$fillable` ou `$guarded` alterado.

## Estado restaurado depois de cada execução

A escrita roda dentro de uma transação que é **sempre desfeita**:

```text
BEGIN
  UPDATE security_lab_resources …      a escrita do cenário
  SELECT …                             o estado que ficou, lido do banco
ROLLBACK
BEGIN
  INSERT security_test_runs            o que o teste observou
  INSERT audit_logs                    quem executou
COMMIT
```

O documento volta sozinho ao estado sintético, então toda execução parte do
mesmo ponto e a ordem dos testes não importa. O que sobrevive é o registro:
`result_context` guarda as propriedades enviadas e o estado antes e depois,
e é dele que a tela tira o resultado.

## Por que não existe um endpoint vulnerável

Uma rota como `PATCH /security/vulnerable/documents/{id}` seria uma escrita
insegura endereçável por URL. No NAPI a atribuição ampla existe **só dentro
do caso de uso** `RunMassAssignmentTest`: roda no processo, contra dados
sintéticos, a pedido de um operator autorizado, e é desfeita depois de
observada. Nenhuma rota do laboratório recebe identificador de recurso na
URL (`RouteCoverageTest`).

## Limitações

- Uma propriedade permitida e uma protegida. O teste não cobre atributos
  aninhados, relações (`sync` de perfis) nem JSON arbitrário.
- O cenário protegido **ignora** a propriedade em silêncio, que é o
  comportamento de `validated()`. Recusar o pedido inteiro (422 com
  `prohibited`) é uma alternativa válida e não é demonstrada.
- A autorização por propriedade aqui é fixa por operação. Regras que
  dependem de quem pede (um revisor pode aprovar, o dono não) pedem uma
  operação própria com a sua Policy, e não fazem parte do teste.
- A leitura de propriedades em excesso, a outra metade da API3:2023, fica
  fora.

## Onde ver

- Interface: `/admin/security/mass-assignment` (com `SECURITY_LAB_ENABLED=true`)
- Código: `app/Domain/Security/Actions/RunMassAssignmentTest.php`
- Testes: `tests/Feature/SecurityLab/MassAssignmentTestExecutionTest.php`
