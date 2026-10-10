# IDOR / BOLA

[← Segurança](README.md) · [Módulo Security Lab](../modules/security.md) · [Mass Assignment](mass-assignment.md) · [ADR-0010](../architecture/decisions/ADR-0010-controlled-security-lab.md) · [Autorização](authorization.md)

## O que é

**IDOR** (Insecure Direct Object Reference) é o nome clássico; **BOLA**
(Broken Object Level Authorization) é o nome da mesma falha no OWASP API
Security Top 10, onde ocupa o primeiro lugar. A aplicação recebe o
identificador de um objeto, encontra o objeto e o devolve, sem perguntar se
quem pediu pode acessar **aquele** objeto.

```text
GET /documentos/{id}

  lookup por id  →  objeto encontrado  →  objeto devolvido
                                           ▲
                          faltou: "este objeto é de quem pediu?"
```

O atacante não quebra a autenticação. Ele está autenticado como ele mesmo e
troca o identificador por outro. A lição central é que **encontrar não é
autorizar**: Route Model Binding e `find($id)` respondem "existe?", nunca
"pode?".

## O cenário do laboratório

```text
Operator  admin@napi.dev          quem executa o teste (sessão real)
   ↓
Actor     Alice                   identidade cuja autorização é simulada
   ↓
Target    Documento B, de Bob     recurso sintético de outra pessoa
```

Alice é dona do Documento A. O teste simula Alice pedindo o Documento B pelo
identificador.

| | Vulnerável | Protegido |
|---|---|---|
| Lookup | `SecurityLabResource::findOrFail($id)` | o mesmo |
| Autorização | nenhuma | `Gate::forUser($actor)->allows('view', $target)` |
| Resposta observada | `allowed` | `denied` |
| Veredito | **EXPOSED** | **PROTECTED** |

A diferença entre os dois cenários é uma linha. Isso é intencional: mostra
que o lookup é idêntico e que a segurança está toda na pergunta seguinte.

### A mitigação

```php
// app/Policies/SecurityLabResourcePolicy.php
public function view(User $actor, SecurityLabResource $resource): bool
{
    return $resource->owner_user_id === $actor->id;
}
```

É o mecanismo idiomático do Laravel, o mesmo que protege o IAM
([autorização](authorization.md#camada-4-regras-contextuais-nas-policies)). A
Policy é de posse pura, sem atalho para nenhum perfil: nem o `admin` lê o
documento de outra persona por ela.

### Operator ≠ Actor

O actor é só contexto do teste. A Policy é avaliada **para** o actor com
`Gate::forUser()`, e ninguém faz `Auth::login($actor)`: a sessão continua
sendo do operator durante e depois da execução. O registro guarda os dois
(`initiated_by_user_id` e `acting_as_user_id`), e a auditoria aponta o
operator.

### O caso do dono

Alice pedindo o próprio Documento A é acesso legítimo, permitido nos dois
cenários. O veredito é **PROTECTED** (nenhum acesso indevido aconteceu), e a
interface mostra "acesso legítimo do dono". O veredito olha o objetivo do
teste: só é exposição quando o actor recebe um recurso que não é dele.

| Actor | Alvo | Cenário | Resposta | Veredito |
|---|---|---|---|---|
| Alice | de Bob | vulnerável | allowed | **exposed** |
| Alice | de Bob | protegido | denied | **protected** |
| Alice | de Alice | qualquer | allowed | **protected** (acesso legítimo) |
| — | — | falha técnica | — | **inconclusive** |

Um erro operacional (alvo inexistente, banco indisponível) é `error` com
veredito `inconclusive`. Uma falha nunca é contada como proteção.

## Risco

Em um sistema real, a mesma falha expõe dados de outros clientes por simples
enumeração de identificadores. ULIDs (usados no NAPI) tornam os
identificadores difíceis de adivinhar, mas isso não é controle de acesso: um
identificador vaza por log, por URL compartilhada ou por outra resposta da
API. A defesa é a verificação de autorização por objeto.

## Por que dados sintéticos

O cenário vulnerável devolve o conteúdo do alvo de verdade. Se o alvo fosse
`users`, `profiles`, tokens ou `audit_logs`, o laboratório seria ele mesmo um
vazamento. Por isso existe `security_lab_resources`: documentos fictícios, com
conteúdo marcado como `Synthetic Security Lab Data`, de personas que não são
contas utilizáveis. A FK `security_test_runs.target_resource_id` só aceita
esse tipo de alvo, e o formulário só aceita personas como actor.

## Por que não existe um endpoint vulnerável

Uma rota como `GET /security/vulnerable/resources/{id}` seria uma
vulnerabilidade real, endereçável por URL, protegida apenas por quem lembrar
de mantê-la protegida. No NAPI a leitura insegura existe **só dentro do caso
de uso** `RunIdorTest`: roda no processo, contra dados sintéticos, a pedido de
um operator autorizado, e registra o que viu. Nenhuma rota do laboratório
recebe identificador de recurso na URL, e um teste garante isso
(`RouteCoverageTest`).

## Onde ver

- Interface: `/admin/security/idor` (com `SECURITY_LAB_ENABLED=true`)
- Código: `app/Domain/Security/Actions/RunIdorTest.php`
- Testes: `tests/Feature/SecurityLab/IdorTestExecutionTest.php`
