# Testes de segurança e invariantes

[← Testes](README.md) · [Invariantes](../security/security-policies.md) · [Threat model](../security/threat-model.md)

Os testes abaixo existem para **impedir regressões de segurança**, não para
aumentar cobertura. Cada linha liga uma invariante ao teste que falharia se
ela quebrasse.

## Invariante → teste

| Invariante | Teste | Arquivo | O que falha se a regra quebrar |
|---|---|---|---|
| INV-01 autenticação obrigatória | `returns 401 when accessing protected route without token` | `IAM/AuthenticationTest` | `/auth/me` acessível sem token |
| Rate limit de login | `rate limits login after 6 attempts in 30 minutes` | `IAM/AuthenticationTest` | 7ª tentativa não recebe 429 |
| Mensagem uniforme | `rejects invalid password` | `IAM/AuthenticationTest` | erro de validação em outro campo |
| Logout revoga tokens | `logs out and revokes tokens` | `IAM/AuthenticationTest` | tokens remanescentes |
| INV-02 só admin gerencia perfis | `blocks viewer from creating profile` | `IAM/ProfileTest` | viewer cria perfil |
| INV-04 perfil de sistema | `blocks deletion of system profile` | `IAM/ProfileTest` | admin exclui o perfil `admin` |
| INV-05 perfil com usuários | `blocks deletion of profile with users`, `prevents deleting a profile that still has users` | `IAM/ProfileTest`, `IAM/AuthorizationTest` | revogação em cascata |
| INV-06 menu com filhos | `blocks deletion of menu with children` | `IAM/MenuTest` | subárvore apagada |
| INV-07 auto-exclusão | `blocks admin from deleting themselves` | `IAM/AuthorizationTest` | admin se exclui |
| INV-08 último admin | `blocks removing last admin` | `IAM/UserProfileSyncTest` | sistema sem admin |
| INV-09 auto-escalação | `blocks user from assigning profiles to themselves` | `IAM/UserProfileSyncTest` | usuário comum se concede perfil |
| INV-10 IDOR em usuários | `prevents user from viewing other users`, `blocks viewer from accessing user endpoints` | `IAM/AuthorizationTest` | leitura de outro usuário |
| INV-11 mass assignment | `has fillable protection` | `UserTest` | atributo arbitrário gravado |
| INV-13 auditoria de privilégio | `logs profile assignment`, `logs permission change` | `IAM/AuditEventTest` | mudança sem trilha |
| INV-14 trilha sobrevive | `force deleting a user nullifies its audit logs instead of deleting them` | `UserTest` | logs apagados com o ator |
| INV-15 e-mail único | `email is unique`, `email is case-insensitive (citext)` | `UserTest` | contas duplicadas |
| Integridade de vínculos | testes de `force deleting … cascades` | `UserTest`, `ProfileTest`, `MenuTest` | pivots órfãos |

## Lacunas

Invariantes e ameaças **sem** teste automatizado. Os itens marcados com ⚠️
foram confirmados em execução na Fase 3.1 como comportamento atual
**inseguro ou incorreto**. Hoje um teste para esses itens falharia, portanto
ele deve ser escrito junto com a correção.

| Lacuna | Relacionado |
|---|---|
| ⚠️ Token com ability `read` executando escrita | FIND-001 |
| ⚠️ Quantidade de linhas de auditoria por evento (`exists()` esconde duplicação) | FIND-002 |
| ⚠️ Token de usuário desativado | FIND-003, INV-16 |
| Login de conta inativa retorna 422 | INV-16 |
| ⚠️ Perfil customizado com permissão na matriz | FIND-004 |
| ⚠️ Renomear ou excluir menu-chave | FIND-005 |
| ⚠️ Último admin com outro admin inativo; corrida concorrente | FIND-006 |
| ⚠️ `dev` listando usuários | FIND-009 |
| `syncMenus` negado para não-admin e para perfil de sistema | INV-03, INV-04 |
| `update` em perfil de sistema | INV-04 |
| Revogar token de outro usuário | INV-12 |
| Campos extras via HTTP (`is_system`, `is_active`) ignorados | INV-11 |
| 401 nas rotas de Profiles, Menus e Tokens | INV-01 |
| Toda rota IAM declara `permission:` (teste de varredura de rotas) | threat model |
| Conteúdo da árvore de menus (filhos, inativos) | FIND-010 |
| Rotas testadas com Bearer token real (`withToken()`), não `actingAs()` | FIND-021 |

Na Fase 3.1, esses comportamentos foram reproduzidos com testes temporários,
que foram **removidos** porque a fase não altera código. Eles são o ponto de
partida natural da [Fase 5](../phases/README.md) e do
[Security Lab](../modules/security.md).
