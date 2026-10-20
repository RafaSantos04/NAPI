# Fase 4.4 — Administração de menus

[← Fases](README.md) · [Autorização](../security/authorization.md#área-administrativa-blade) · [Módulo IAM](../modules/iam.md) · [Fase 4.3](phase-04-3-profile-permissions.md)

**Commit:** pending

Executada junto com a [4.3](phase-04-3-profile-permissions.md), depois da
Fase 5.2.

## Objetivo

Administrar pela área Blade a estrutura de menus usada pelo IAM: a chave de
cada menu é a identidade das permissões da área, e todo o resto é
navegação. A fase também fecha o
[FIND-019](../findings/README.md#find-019--menu-pode-ser-pai-de-si-mesmo):
um menu não pode mais ficar abaixo de si mesmo.

## Reality check

| Pergunta | Encontrado |
|---|---|
| `key` imutável? | Sim, para **todos** os menus: `MenuUpdateRequest` a declara `prohibited` (Fase 3.2). Mais forte que "imutável só nos de sistema", e foi mantido |
| O que `is_system` protege? | Exclusão (`MenuPolicy::delete`). Rótulo, rota, posição e status continuam editáveis: são navegação |
| `parent_id` validado? | Só com `exists`. Aceitava o próprio id e descendentes (FIND-019); a API também não deixava voltar um menu para a raiz (`null` falhava no `exists`) |
| A árvore suporta vários níveis? | Sim: `/api/v1/menus/tree` monta qualquer profundidade a partir das raízes e já ignorava ciclos |
| CRUD de menu era auditado? | Não ([FIND-012](../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria)) |
| Exclusão de menu com filhos | Policy recusa, fora de transação; `menus.parent_id` tem `cascade` |
| A barra lateral do admin vem de `menus`? | Não. `AdminNavigation` lista seções no código, cada uma visível pela sua Policy |

## Engineering Gate

| Componente | Precisa existir? | Já existia? | Laravel/PHP/PostgreSQL resolvia? | Decisão |
|---|---|---|---|---|
| Actions `CreateMenu`, `UpdateMenu`, `DeleteMenu` | Sim: gravação e auditoria juntas, nos dois canais | Não | `DB::transaction`, `lockForUpdate` | **Criadas**; a API passou a usá-las |
| DTOs `CreateMenuDto`, `UpdateMenuDto` | Sim: `UpdateMenuDto` não tem `key` e filtra no construtor, então nenhum chamador muda a chave | Padrão dos DTOs de usuário | `Arr::only` | **Criados** |
| Eventos `MenuCreated`, `MenuUpdated`, `MenuDeleted` | Sim | Contrato `Auditable` | — | **Criados** |
| Validação de ciclo | Sim (FIND-019) | Não | **CTE recursiva do PostgreSQL** (`WITH RECURSIVE … UNION`), que termina mesmo sobre um ciclo antigo | **Criada** em `MenuUpdateRequest`, valendo para API e admin; consulta em `Menu::isSelfOrAncestorOf()` |
| Pacote de nested set / closure table | Não | — | `parent_id` + CTE | **Não introduzido** |
| CHECK `parent_id <> id` | Sim: garantia no banco para o caso direto | Não | CHECK do PostgreSQL | **Criado** (migration aditiva, reversível). Ciclos longos dependem de outras linhas e ficam na validação |
| Trigger contra ciclos longos | Não: a árvore já é montada das raízes e não entra em loop | — | — | **Não criado** |
| `Menu::hierarchy()` | Sim: lista e seletor de pai precisam da árvore em ordem, com profundidade | Lógica parecida em `Api\V1\MenuController::branch` (árvore filtrada por permissão, outro formato) | uma consulta + agrupamento | **Criado** no model, com a relação `parent` preenchida do mesmo conjunto (sem N+1) |
| `ResourceInUseException` | Sim | Criada na 4.3 | — | **Reutilizada** (`menuHasChildren`) |
| Policy nova | Não | `MenuPolicy` | — | **Reutilizada** sem mudança |
| Requests web | Sim | Padrão da 4.2 | — | **Criadas** 2 (`Store`, `Update`) |
| Controller web | Sim | Não | — | **Criado** `MenuController`, fino |
| Paginação da lista | Não: submenu só faz sentido sob o pai; a estrutura tem dezenas de linhas | — | — | **Não paginada**, uma consulta só (teste de contagem de consultas) |
| Barra lateral lida de `menus` | Não: as seções precisam de rotas e controllers; acoplar ao `is_active` poderia tirar o acesso ao admin de alguém que só tem aquela seção | `AdminNavigation` | — | **Não feito**; seções Perfis e Menus acrescentadas ao código |
| Drag-and-drop, JS | Não | — | `order` numérico | **Não criado** |
| Painel "perfis do menu" (a matriz vista pelo menu) | Sim: pedido na revisão da fase, para vincular e desvincular perfis no editar menu | A matriz por perfil (4.3): `ProfilePolicy::syncMenus`, `SyncMenuPermissions`, `PermissionChanged` | — | **Reutiliza** Policy, Action e evento; **cria** só o que é do novo ponto de entrada: `SyncMenuProfiles` (orquestra os perfis alterados numa transação), `SyncMenuProfilesDto`, `SyncProfilesRequest`, `MenuPermissionController` e `Profile::matrixWith()` |
| Endpoint equivalente na API | Não: a API já edita a mesma matriz por perfil | `PUT /api/v1/profiles/{profile}/menus` | — | **Não criado** |
| Coluna "Rota" trocada por URL | Avaliado na revisão: o nome de rota não é segredo, e guardar URL livre permitiria link externo ou `javascript:` | — | — | **Mantida** a coluna Rota, por decisão do usuário |

## Menus

- **Lista** (`/admin/menus`): a hierarquia inteira em ordem de navegação,
  com submenus recuados sob o pai; chave, rota, pai, ordem, status e tipo.
  Menus que nenhuma raiz alcança (só possível com ciclo gravado antes desta
  fase) aparecem no fim, para poderem ser corrigidos.
- **Criação**: chave (formato `^[a-z0-9]+(-[a-z0-9]+)*$`, única), rótulo,
  rota (única), ícone, menu pai, ordem, status e descrição. O menu nasce
  personalizado; `is_system` não é campo. A chave nova vira na hora uma área
  de permissão: só o Administrador a tem, implicitamente.
- **Edição**: a chave aparece como texto, sem campo. O backend a recusa por
  três caminhos: a request (`prohibited`, 422), o DTO (que não carrega a
  chave) e o fato de nenhum formulário a enviar.
- **Detalhe**: chave, as quatro permissões derivadas dela, quantos perfis
  concedem algo na área, pai, submenus e status.
- **Exclusão**: só de menu personalizado sem submenus (INV-06). A
  confirmação diz quantos perfis perdem permissões da área. `DeleteMenu`
  confere os submenus de novo com a linha travada (`parent_id` tem
  `cascade`).

## Hierarquia

- `parent_id` precisa existir e não pode ser o próprio menu nem nenhum
  descendente, em qualquer profundidade (`MenuUpdateRequest`, API e admin).
- `null` volta o menu para a raiz (a API passou a aceitar).
- O seletor de pai do formulário de edição já deixa de fora o menu e o ramo
  dele; a validação continua no backend.
- No banco, `menus_parent_not_self_check` recusa `parent_id = id` mesmo
  fora da aplicação.

## Perfis de um menu

Acrescentado na revisão da fase. O editar menu mostra, abaixo do formulário,
a linha daquele menu na matriz de cada perfil: perfis × Ver, Criar, Alterar
e Excluir. Marcar vincula o perfil ao menu; desmarcar as quatro ações
desvincula (a linha de `menu_profiles` é removida).

- É a mesma matriz do detalhe do perfil, vista pelo outro lado. Salvar
  (`PUT /admin/menus/{menu}/permissions`, `permissions.update`) passa cada
  perfil **que mudou** por `ProfilePolicy::syncMenus`, com a matriz inteira
  que ele teria, e grava com `SyncMenuPermissions`. As regras são as mesmas:
  perfil de sistema imutável, ninguém além do Administrador altera um perfil
  que tem, ninguém concede flag que não tem.
- Tudo numa transação: se um dos perfis do envio for recusado, nenhum muda.
- Perfil cuja linha não mudou não é gravado nem auditado. Cada perfil
  alterado gera um `permission_changed`, igual ao da página do perfil.
- Linhas que o ator não pode alterar (sistema, o próprio perfil, perfis com
  flags que ele não tem) aparecem só para leitura e não são enviadas; o
  Administrador aparece como "todas, implicitamente".
- Quem não tem `permissions.update` vê o painel só para leitura.

## Chave estável × navegação

| Campo | Papel | Editável |
|---|---|---|
| `key` | identidade da permissão (`{key}.{ação}`) | não, nunca |
| `label`, `route_name`, `icon`, `order`, `parent_id` | navegação | sim |
| `is_active` | navegação: inativo some de `/api/v1/menus/tree` | sim; as permissões continuam valendo |
| `is_system` | protege a chave usada pelas rotas contra exclusão | não |

Teste de regressão: trocar rótulo e rota do menu `users` pelo admin não muda
nada para quem tem `users.view`.

## Navegação

- A árvore da API continua sendo a projeção da matriz: menu inativo some,
  filho sem `{key}.view` some, filho de pai invisível some. Testado com
  menus criados e alterados pelo admin.
- A barra lateral do admin ganhou **Perfis** e **Menus**, cada uma visível
  pela própria Policy (`viewAny`). Quem só tem `menus.view` entra no admin e
  vê só Menus.

## Auditoria

| Ação | Evento | Meta |
|---|---|---|
| `menu_created` | `MenuCreated` | — |
| `menu_updated` | `MenuUpdated` | `fields` (nomes); sem mudança, sem registro |
| `menu_deleted` | `MenuDeleted` | `key`, porque a linha deixa de existir |

## Banco de dados

| Mudança | Migration |
|---|---|
| CHECK `menus_parent_not_self_check` (`parent_id IS NULL OR parent_id <> id`) | `2026_10_20_013946_add_parent_not_self_check_to_menus_table` |

Verificada no `napi_test` com `migrate:fresh`, `migrate:rollback --step=1`
(constraint removida), `migrate` e `migrate:fresh --seed`. O banco de
desenvolvimento precisa de `php artisan migrate`; ele não tem menu com
`parent_id = id`, então a migration passa.

## Testes

| Arquivo | Testes |
|---|---|
| `tests/Feature/Admin/MenuManagementTest.php` (novo) | 42 (8 do painel de perfis) |
| `tests/Feature/Security/AuditIntegrityTest.php` | +1 (CRUD de menu pela API auditado uma vez cada) |

Mutações das Fases 4.3 e 4.4 (14, todas detectadas depois de reforçar três
testes): regra de ciclo removida; consulta de ancestralidade sempre falsa;
`DeleteProfile` e `DeleteMenu` sem a reconferência; `key` aceita na
atualização; `UpdateMenuDto` carregando a chave; Actions sem auditoria;
flags da matriz sem normalização para booleano; seção Perfis sumida;
Policy deixando não-admin conceder o que não tem; seletor de pai oferecendo
o próprio ramo; rota da matriz pedindo `profiles.view`; edição aceitando
campo omitido.

O painel de perfis passou por mais quatro mutações, todas detectadas:
request autorizando tudo, perfis sem mudança também gravados, linha vazia
mantida em vez de desvinculada, rota pedindo `menus.update`.

**Checkpoint visual**: servidor real sobre `napi_test`; criação de perfil e
de submenu inativo, gravação da matriz e erros de validação por HTTP; telas
no Edge headless a 1600 px e num iframe de 390 px. O painel de perfis foi
gravado por HTTP e conferido a 1400 px e a 390 px. A matriz e o aviso de
"Ativo" foram ajustados depois da primeira captura. Não houve teste em
navegador interativo.

## Findings

| Finding | Na Fase 4.4 |
|---|---|
| [FIND-019](../findings/README.md#find-019--menu-pode-ser-pai-de-si-mesmo) | **RESOLVED** |
| [FIND-012](../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria) | CRUD de menu auditado; resta o login falho |
| [FIND-026](../findings/README.md#find-026--bytes-inválidos-em-utf-8-num-formulário-viram-erro-500) | **Novo, OPEN**: bytes inválidos em UTF-8 num formulário do admin viram 500 |

## Fora do escopo

Reordenação por arrastar; ícones visuais; barra lateral do admin gerada a
partir de `menus`; menus de área sem tela (`audit-logs`).
