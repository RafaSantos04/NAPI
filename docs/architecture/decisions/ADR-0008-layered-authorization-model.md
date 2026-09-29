# ADR-0008 — Modelo de autorização em camadas

[← ADRs](README.md) · [Autorização](../../security/authorization.md) · [Threat model](../../security/threat-model.md)

## Status

Accepted (Fase 3.2). Supersedes [ADR-0004](ADR-0004-policy-based-authorization.md) e [ADR-0006](ADR-0006-route-permission-matrix.md).

## Context

A Fase 3.1 mostrou que a autorização do NAPI não tinha uma resposta única
para "este ator pode fazer esta ação?":

- a matriz perfil × menu (ADR-0006) e as Policies por slug (ADR-0004) eram
  fontes independentes, e o acesso efetivo era a interseção. Perfis
  customizados configurados pela matriz não tinham efeito ([FIND-004](../../findings/README.md#find-004--duas-fontes-de-autorização-que-não-se-conhecem));
- a chave de autorização era o `route_name`, um campo de navegação editável.
  Renomear `menus.index` trancava o admin ([FIND-005](../../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema));
- `User::hasPermission()` era um stub que retornava `true` ([FIND-007](../../findings/README.md#find-007--userhaspermission-é-um-stub-que-sempre-autoriza));
- as abilities de token eram gravadas e nunca verificadas ([FIND-001](../../findings/README.md#find-001--abilities-de-token-não-são-aplicadas));
- usuários desativados continuavam autenticados ([FIND-003](../../findings/README.md#find-003--usuário-desativado-continua-autenticado));
- a regra do último admin tinha corrida e respondia 500 ([FIND-006](../../findings/README.md#find-006--regra-do-último-administrador-tem-janela-de-corrida-e-resposta-500)).

A Fase 4 (área administrativa) depende de perfis configuráveis que
funcionem, então isso precisava ser decidido antes.

## Decision

A autorização passa a ser composta por **cinco camadas**, cada uma com uma
pergunta e um único dono. Nenhuma camada concede o que outra negou.

| # | Camada | Pergunta | Implementação | Negação |
|---|---|---|---|---|
| 1 | Autenticação | Quem é, e a conta está ativa? | `auth:sanctum` + `Sanctum::authenticateAccessTokensUsing` (dono ativo) | 401 |
| 2 | Capacidade do token | O token pode este tipo de operação? | `EnsureTokenAbility`: GET→`read`, POST/PUT/PATCH→`write`, DELETE→`delete`, em todo o grupo autenticado | 403 |
| 3 | Permissão funcional | O perfil pode esta ação nesta área? | `User::hasPermission('{key}.{ação}')` sobre `menu_profiles` × `menus.key`; `permission:` na rota | 404 |
| 4 | Regra contextual | Pode, neste recurso e para este ator? | Policies, que chamam `hasPermission()` e acrescentam regras de instância e anti-escalação | 403 (404 em `index`/`show`) |
| 5 | Invariante de domínio | O estado continua válido? | `EnsureActiveAdministratorRemains` em transação com lock | 409 |

Decisões específicas:

1. **Uma única fonte funcional.** `User::hasPermission()` é a única resposta
   para "o perfil pode a ação X na área Y". O middleware, as Policies e a
   árvore de navegação a consultam. As Policies deixam de testar slugs para
   a parte funcional.
2. **Identidade estável de permissão.** `menus.key` (único, imutável, formato
   `[a-z0-9-]`) é o identificador de permissão. `route_name`, `label` e
   `is_active` passam a ser só navegação. Menus cujas chaves as rotas
   referenciam são `is_system` e não podem ser excluídos.
3. **Admin como superusuário funcional.** O perfil de sistema `admin` detém
   todas as permissões funcionais, inclusive de chaves futuras. Sua matriz
   não é editável (perfil de sistema). Admin **não** ignora as camadas 2, 4 e 5.
4. **Delegação sem escalação.** Um perfil customizado pode receber
   `users.update` ou `permissions.update`, mas quem não é admin só concede
   perfis e flags que já tem, não altera os próprios perfis nem a matriz de
   um perfil que possui, e não mexe em contas de administrador.
5. **Abilities limitam, nunca concedem.** O contrato de abilities existente
   (`read`, `write`, `delete`) foi mantido e passou a ser aplicado pelo
   método HTTP. Um token só emite tokens com um subconjunto das próprias
   abilities.
6. **Invariante de administrador.** "Existe pelo menos um usuário ativo e
   não excluído com o perfil `admin`" é aplicada no domínio, em transação,
   com `SELECT … FOR UPDATE` na linha do perfil admin, e a violação é
   `LastActiveAdministratorException` → 409.
7. **Navegação é projeção.** `GET /menus/tree` mostra os menus ativos em que o
   ator tem `{key}.view`, em qualquer profundidade, e é acessível a qualquer
   autenticado. Não é um mecanismo de segurança.

## Rationale

- **Uma pergunta por camada** torna a autorização explicável: para qualquer
  negação é possível dizer qual camada negou e por quê. A Fase 3.2 exigia
  poder responder "quem é, o que o token permite, o que o perfil permite, o
  que a Policy permite, qual invariante impede".
- **Matriz como fonte funcional** mantém o que o ADR-0006 pretendia
  (configuração por dados, sem deploy) e elimina a interseção silenciosa com
  as Policies. As Policies ficam com o que só código expressa bem: regras
  sobre a instância e sobre a relação entre ator e alvo.
- **`key` separada de `route_name`** é a alternativa que o próprio ADR-0006
  registrou como não feita. Ela resolve o lockout sem mover autorização para
  a interface.
- **Admin implícito** evita duas armadilhas: o admin sem acesso a menus novos
  (a matriz dele era bloqueada por ser de sistema) e o lockout por edição da
  matriz. O slug `admin` já era um conceito de código por causa da
  invariante; agora ele tem uma semântica única e documentada.
- **Ability por método HTTP** cobre toda rota nova por padrão, em vez de
  depender de lembrar de declarar `abilities:` em cada rota.
- **404 para negação funcional** preserva o raciocínio anti-enumeração do
  ADR-0006. Regras contextuais continuam 403, porque quem as atinge já tem
  acesso funcional à área.

## Alternatives Considered

- **Policies como fonte única, sem middleware.** A negação passaria a ser 403
  nas mutações, o que quebra a convenção de não revelar a área (ADR-0006).
  Descartada.
- **Tabela `permissions` separada de `menus`.** Separaria completamente
  navegação e autorização, mas exige nova tabela, nova matriz e migração dos
  dados. A coluna `key` resolve o problema concreto com uma mudança aditiva.
- **Pacote de RBAC (spatie/laravel-permission).** Fora do escopo; o NAPI
  mantém o modelo próprio.
- **Admin só pela matriz**, com a matriz do admin editável: mais superfície de
  lockout e a necessidade de proteger as próprias permissões do admin.
- **Middleware `abilities:` do Sanctum por rota.** Explícito, mas sujeito a
  esquecimento em rotas novas.
- **Checar `is_active` num middleware.** Cobriria também sessões, mas seria um
  segundo ponto além da autenticação; o callback do Sanctum decide no
  próprio momento da autenticação e responde 401, que é o status correto.

## Consequences

### Positive

- Perfis customizados funcionam, e a matriz é a única fonte funcional.
- Renomear ou desativar um menu não afeta a autorização.
- Tokens com escopo reduzido de fato são reduzidos.
- A invariante de administrador resiste a concorrência e responde 409.
- `hasPermission()` pode ser usada pela área Blade da Fase 4 sem risco de
  fail-open.

### Negative

- O slug `admin` continua com significado em código (superusuário funcional e
  invariante). Renomear o slug do perfil admin não é suportado; ele já é um
  perfil de sistema, imutável pela API.
- A permissão funcional ainda é declarada por rota (`permission:`). Uma rota
  nova sem `permission:` e sem Policy fica acessível a qualquer autenticado
  (com a ability correta). Não há teste que varra as rotas.
- `index` e `show` ainda chamam a Policy à mão nos controllers para negar
  com 404 ([FIND-020](../../findings/README.md#find-020--respostas-de-autorização-e-de-tokens-inconsistentes)).
- `dev` perde a listagem de usuários, que era efeito colateral da linha
  `users.view` no seed. O autoatendimento continua em `/auth/me`.
- As colunas `key` e `is_system` entraram na migration de criação de `menus`, porque ainda não há produção. Bancos existentes precisam de `migrate:fresh --seed`.

## Security Impact

Mitiga escalação de privilégio por token de escopo reduzido, por delegação e
por auto-atribuição; lockout administrativo por perfis, por menus e por
concorrência; acesso de contas desativadas; divergência entre listagem e
detalhe. Veja a seção [Mitigações da Fase 3.2](../../security/threat-model.md#mitigações-da-fase-32)
do threat model.

## References

- `app/Models/User.php` (`hasPermission`, `permissionKeys`, `holdsPermissionsOf`), `app/Models/Menu.php` (`permissionFor`), `app/Models/MenuProfile.php` (`ACTIONS`)
- `app/Http/Middleware/EnsureTokenAbility.php`, `app/Http/Middleware/CheckPermission.php`
- `app/Policies/*`
- `app/Domain/IAM/EnsureActiveAdministratorRemains.php`, `app/Domain/IAM/Exceptions/LastActiveAdministratorException.php`
- `app/Providers/AppServiceProvider.php` (`authenticateAccessTokensUsing`)
- `database/migrations/2026_09_20_005741_create_menus_table.php`
- `tests/Feature/Security/*`
