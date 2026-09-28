# Threat model inicial

[← Segurança](README.md) · [Findings](../findings/README.md) · [Security Lab](../modules/security.md)

Escopo: **somente o que existe hoje**, a API REST v1 do IAM. Nenhuma
mitigação nova foi implementada nesta fase.

**Status:** ✅ mitigado (há evidência no código e, idealmente, teste) · ⚠️ parcial · ❌ não mitigado.
"Confirmado em execução" indica que o comportamento foi reproduzido na Fase
3.1 com um teste temporário, removido em seguida.

**Fases relacionadas:** "Fase 4" é a área administrativa Blade; "Fase 5" é
a área de segurança/testes; "Hardening" indica correção sem fase definida.
Veja [phases](../phases/README.md).

## Ativos e atores

- **Ativos**: tokens, matriz de permissões, atribuições de perfil, dados de
  usuários, trilha de auditoria.
- **Atores**: anônimo; usuário autenticado sem perfil; `viewer`; `dev`;
  `admin`; portador de um token vazado.

## Autenticação

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Brute force em uma conta | ⚠️ | `throttle:login`, 6 tentativas / 30 min por **email + IP** | ✅ `rate limits login…` | Tentativas distribuídas por IPs não são limitadas; não há bloqueio de conta nem alerta | Hardening |
| Credential stuffing (muitos e-mails, um IP) | ❌ | A chave inclui o e-mail, então cada e-mail novo tem cota própria | ❌ | Alto volume por IP é possível ([FIND-016](../findings/README.md#find-016--rate-limiting-restrito-ao-login-e-por-emailip)) | Hardening |
| Vazamento de token | ⚠️ | Hash SHA-256 no banco; prefixo `napi_` para secret scanning; expiração de 7 dias; revogação | parcial (revogação) | O token vazado tem poder total do usuário, porque as abilities não são aplicadas (confirmado em execução, [FIND-001](../findings/README.md#find-001--abilities-de-token-não-são-aplicadas)) | Hardening |
| Replay de token | ⚠️ | Expiração global de 7 dias; logout revoga todos | ✅ logout | Válido por até 7 dias sem rotação nem vínculo com IP ou dispositivo | Hardening |
| Usuário desativado com token válido | ❌ | Só o login bloqueia inativos | ❌ | Acesso continua até expirar ou ser revogado (confirmado em execução, [FIND-003](../findings/README.md#find-003--usuário-desativado-continua-autenticado)) | Hardening |
| Usuário excluído (soft delete) com token | ✅ | O Sanctum não resolve `tokenable` excluído | ❌ | Linhas de token órfãs permanecem | — |
| Enumeração de usuários pelo login | ⚠️ | Mensagem única `Invalid credentials.` | ✅ senha inválida | Diferença de tempo (`exists:users` evita o bcrypt); `Account is inactive.` confirma credenciais válidas ([FIND-018](../findings/README.md#find-018--sinais-de-enumeração-no-login)) | Fase 5 |

## Autorização

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Auto-escalação (atribuir perfis a si) | ✅ | Matriz `users.index,update` + `assignProfiles` | ✅ | — | — |
| Escalação via matriz (dar permissão ao próprio perfil) | ✅ | Matriz `permissions.index,update` + `syncMenus` | ⚠️ só caminho positivo | Falta teste de negação | Fase 5 |
| IDOR em `GET /users/{id}` | ✅ | `UserPolicy::view` + 404 | ✅ | — | — |
| Exposição via listagem | ❌ | — | ❌ | `dev` lista todos os usuários com e-mail ([FIND-009](../findings/README.md#find-009--perfil-dev-lista-todos-os-usuários-mas-não-pode-ver-nenhum)) | Hardening |
| IDOR em tokens | ✅ | Consulta escopada no dono | ❌ | Falta teste | Fase 5 |
| Mass assignment | ✅ | `validated()`, `#[Fillable]`, `shouldBeStrict()` fora de produção | ⚠️ só no model | Em produção, atributos não fillable são descartados em silêncio | Fase 5 |
| Bypass de Policy por rota nova sem middleware | ⚠️ | Toda rota IAM atual declara `permission:` e usa Policy | ❌ | Nada impede estruturalmente uma rota nova sem os dois; não há teste que varra as rotas | Fase 5 |
| Confiança indevida na interface (menu oculto) | ✅ | O backend aplica matriz + Policy em toda rota | ✅ indireto | A árvore expõe nomes de menus filhos sem `can_view` ([FIND-010](../findings/README.md#find-010--árvore-de-menus-não-filtra-filhos-nem-inativos)) | Fase 4 |
| Adulteração de perfis de sistema | ✅ | `is_system` em `update`/`delete`/`syncMenus`; campo não aceito na entrada | ⚠️ só a exclusão | — | Fase 5 |
| Lockout administrativo via perfis | ⚠️ | INV-07, INV-08, INV-04 | ✅ | Corrida e admins inativos ([FIND-006](../findings/README.md#find-006--regra-do-último-administrador-tem-janela-de-corrida-e-resposta-500)) | Hardening |
| Lockout administrativo via menus | ❌ | — | ❌ | Renomear ou excluir `menus.index`/`users.index` tranca o admin (confirmado em execução, [FIND-005](../findings/README.md#find-005--menus-são-chaves-de-autorização-sem-proteção-de-sistema)) | Hardening |
| Escopo excessivo de token | ❌ | — | ❌ | [FIND-001](../findings/README.md#find-001--abilities-de-token-não-são-aplicadas) | Hardening |

## Entrada

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Campos inesperados | ✅ | Só `validated()` é persistido | ❌ HTTP | — | Fase 5 |
| SQL injection | ✅ | Eloquent com bindings; a única interpolação (`menu_profiles.can_{$action}`) usa valor fixo da definição da rota | ❌ | — | — |
| Referências inválidas | ⚠️ | `exists:` em `profile_ids.*` e `parent_id` | ❌ | Chaves de `permissions` não validadas: menu inexistente gera 500 (confirmado em execução, [FIND-011](../findings/README.md#find-011--validação-incompleta-na-sincronização-de-permissões)); menu pode ser pai de si mesmo ([FIND-019](../findings/README.md#find-019--menu-pode-ser-pai-de-si-mesmo)) | Hardening |
| Dados excessivos | ⚠️ | `max:` em strings; paginação padrão (15) | ❌ | `profile_ids` e `permissions` sem limite de tamanho | Hardening |

## API

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Ausência de rate limit | ❌ | Só o login é limitado | ❌ | Endpoints autenticados sem limite ([FIND-016](../findings/README.md#find-016--rate-limiting-restrito-ao-login-e-por-emailip)) | Hardening |
| Enumeração de recursos | ✅ | ULIDs; 404 uniforme para "sem permissão" e "inexistente" | ✅ | Tokens usam `bigint`, mas com escopo no dono | — |
| Exposição excessiva | ⚠️ | Resources com lista explícita de campos; `password` oculto; `user_details` não exposto | ⚠️ | Listagem para `dev`; árvore com filhos | Hardening |
| Respostas inconsistentes | ⚠️ | — | — | 404 vs 403 vs 500 para negações e regras de negócio ([FIND-020](../findings/README.md#find-020--respostas-de-autorização-e-de-tokens-inconsistentes)) | Hardening |
| Vazamento de detalhes em erro | ⚠️ | Em produção (`APP_DEBUG=false`) o 500 é genérico | ❌ | Regra de negócio como 500 polui logs de erro e confunde o cliente | Hardening |

## Auditoria

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Ações sem trilha | ⚠️ | Login, logout, exclusão de usuário, atribuição de perfis e mudança de permissões | ✅ para 2 ações | CRUD, tokens e login falho sem trilha ([FIND-012](../findings/README.md#find-012--lacunas-de-cobertura-da-auditoria)) | Hardening |
| Metadados insuficientes | ⚠️ | Ator, IP e subject na maioria | ❌ | Sem UA nos eventos IAM; sem estado anterior; `subject_type` inconsistente | Hardening |
| Registros duplicados | ❌ | — | ❌ | Duas linhas por evento (confirmado em execução, [FIND-002](../findings/README.md#find-002--listeners-de-auditoria-registrados-em-duplicidade)) | Hardening |
| Informação sensível no log | ✅ | Sem senhas ou tokens em `meta` | ❌ | IP/UA sem política de retenção (LGPD) | Hardening |
| Alteração dos próprios logs | ❌ | Nenhum endpoint de escrita | ❌ | Mutável no model e no banco ([FIND-014](../findings/README.md#find-014--audit_logs-é-mutável)) | Hardening |

## Dados pessoais

| Ameaça | Status | Mitigação existente | Teste | Risco residual | Fase |
|---|---|---|---|---|---|
| Reversão de CPF | ❌ | CPF guardado como SHA-256 | ❌ | SHA-256 sem salt em um espaço de ~10⁹ valores é reversível por força bruta ([FIND-008](../findings/README.md#find-008--hash-de-cpf-é-reversível-por-força-bruta)) | Hardening |
