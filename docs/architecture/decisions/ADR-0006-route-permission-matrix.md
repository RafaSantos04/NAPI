# ADR-0006 — Matriz perfil × menu como autorização por rota, negando com 404

[← ADRs](README.md) · [Autorização](../../security/authorization.md) · [ADR-0004](ADR-0004-policy-based-authorization.md)

## Status

Accepted (registrado retroativamente na Fase 3.1; a matriz existe desde a Fase 2)

## Context

O IAM precisa responder "este perfil pode ver, criar, alterar ou excluir
nesta área do sistema?" de forma configurável por dados, sem deploy.

## Decision

- A tabela `menu_profiles` guarda, para cada par (menu, perfil), as flags
  `can_view`, `can_create`, `can_update` e `can_delete`, todas com
  `default(false)`.
- O `route_name` de cada menu é a **chave de autorização**. As rotas da API
  declaram `->middleware('permission:{route_name},{ação}')`.
- `CheckPermission` verifica se algum perfil do ator tem a flag no menu com
  aquele `route_name`. Se não tiver, responde **404** (`{"message":"Not found."}`).
- A mesma matriz alimenta `GET /menus/tree`, que decide quais menus o usuário
  vê.

## Rationale

Evidência no código:

- O comentário em `CheckPermission` diz "Retorna 404 para não revelar que a
  rota existe". O mesmo raciocínio aparece em `UserController::index`: "um
  endpoint de leitura não deve revelar que o recurso existe para quem não
  tem permissão de vê-lo".
- O comentário sobre `wherePivot()` em `CheckPermission` registra uma lição
  aprendida: dentro de `whereHas()`, a coluna do pivot precisa ser qualificada
  (`menu_profiles.can_*`).

Propriedades que o desenho proporciona:

- **Deny by default**: sem linha na matriz, sem acesso.
- Permissões configuráveis por dados, por ação CRUD.
- **A visibilidade de menu não é o mecanismo de segurança.** O que protege a
  API é o middleware no backend, que consulta a mesma matriz em toda
  requisição. Esconder um item no menu é consequência da permissão, não a
  proteção.

## Alternatives Considered

- `can:` middleware nativo do Laravel com Gates por ação: exigiria código por
  permissão, sem configuração por dados.
- Tabela de permissões desacoplada de menus (`permissions` com nome
  próprio): separaria navegação de autorização. Não foi feito, e isso gera
  a consequência negativa abaixo.

## Consequences

### Positive

- Um único lugar configura quem acessa cada área.
- A resposta 404 uniforme dificulta mapear a superfície da API por quem não
  tem acesso.

### Negative

- **Menus são chaves de autorização, mas são editáveis e excluíveis como
  dados comuns.** Um admin pode renomear `menus.index` e perder o acesso ao
  gerenciamento de menus pela API; não há recuperação sem acesso ao banco.
  Além disso, o perfil `admin` (`is_system`) não pode receber permissões em
  menus novos, porque `syncMenus` bloqueia perfis de sistema
  ([FIND-005](../../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema)).
- A matriz sozinha não basta: as Policies ainda exigem `admin` ou `dev`
  ([FIND-004](../../findings/README.md#find-004--duas-fontes-de-autorização-que-não-se-conhecem)).
- O 404 dificulta o diagnóstico legítimo: o cliente não distingue "não
  existe" de "sem permissão".
- Uma consulta com `whereHas` por requisição protegida. Hoje o custo é
  aceitável; não há cache.

## Security Impact

Implementa least privilege configurável e negação por padrão na borda da
API. Mitiga enumeração de endpoints por atores sem permissão. Introduz risco
de lockout administrativo por alteração de dados.

## References

- `app/Http/Middleware/CheckPermission.php`
- `database/migrations/2026_09_20_005742_create_menu_profiles_table.php`
- `database/seeders/MenuSeeder.php`
- `routes/api.php`
- `tests/Feature/IAM/AuthorizationTest.php`, `ProfileTest.php`, `UserProfileSyncTest.php`
