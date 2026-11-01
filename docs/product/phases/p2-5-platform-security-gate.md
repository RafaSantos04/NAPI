# P2.5 — Platform Security Gate

[← Produto](../README.md) · [Roadmap](../roadmap.md) · [ADR-0013](../../architecture/decisions/ADR-0013-abuse-limits-and-expensive-operations.md) · [P2](p2-projects-applications-environments.md)

**Status:** implementada e validada · **JUDGE:** concluído (2026-10-30) · **Commits:** `8cfde21` em diante

## Executive Summary

O P2.5 fecha o abuso da plataforma antes do primeiro import OpenAPI (P3).

- **Senha.** Toda rota que confere senha passa por uma Action:
  `VerifyLoginCredentials`. Ela atende o login da API, o `/admin/login` e a
  confirmação de senha do admin.
- **Contadores.** São três, com as mesmas chaves nas duas pontas:
  identidade + IP, identidade e IP. Alternar entre admin e API não soma
  cota.
- **Corrida.** A decisão sai do valor devolvido pelo incremento atômico,
  então requisições simultâneas não passam juntas.
- **Conta privilegiada.** É a que entra no admin. Ela não troca senha nem
  token por um token novo na API: o token dela nasce no admin, com a senha
  confirmada há no máximo 5 minutos.
- **Rotas autenticadas.** Todas têm limite de 120 requisições por minuto
  por usuário, e a cota soma API e sessão do admin.
- **Entrada.** Todo corpo tem teto de 64 KB, e texto que não é UTF-8 é
  recusado antes de qualquer outra camada. As listas e as senhas têm tetos
  do tamanho do domínio.

O gate do roadmap está cumprido. O FIND-016 foi resolvido, e o orçamento de
tentativas é provadamente único entre admin e API. Os números das operações
caras ficam para o P3, como o ADR-0013 previa.

## Reality Check

**Antes do P2.5:**

- o login da API e o do admin tinham limiters independentes, com chaves
  que diferenciavam maiúsculas no e-mail;
- a decisão do limiter era tomada por leitura seguida de incremento;
- o 429 saía sem `Retry-After`;
- as rotas autenticadas não tinham nenhum limite;
- `TRUSTED_PROXIES` não estava configurado;
- o login revelava contas inativas;
- conta privilegiada recebia por senha um token de 7 dias com todas as
  abilities;
- não havia teto de corpo, e UTF-8 inválido virava 500.

A revisão Admin × API, feita a partir dos comentários do time nos cards,
acrescentou a task T06 e quatro findings (FIND-033 a 036). A ordem
executada foi T01 → T02 → T06 → T03 → T04 → T05.

## Escopo entregue

| Task | Entrega | Commits |
|---|---|---|
| T01 | ADR-0013 (proteção de credenciais, conta privilegiada, rotas autenticadas, proxies, corpo, entrada, operações caras); FIND-027 a 036 | `8cfde21`, `1f25137` |
| T02 | `VerifyLoginCredentials` com 3 contadores atômicos; `TooManyLoginAttemptsException` (429 na API, formulário no admin); limiter `authenticated`; `config/trustedproxy.php`; resposta uniforme no login | `b4ff7de`, `20259b5`, `e735446`, `9dd6f20` |
| T06 | `PersonalAccessTokenPolicy::issueViaApi`; Actions `IssueAccessToken` e `RevokeAccessToken`; `/admin/tokens` com `password.confirm` de 5 min; teto do token derivado; `sanctum:prune-expired` diário | `0bb8381`, `7cb7978`, `bb5f20b` |
| T03 | `LimitRequestBodySize` (64 KB, também sem `Content-Length`); `max:` em listas, senhas e e-mails; `GET /tokens` paginado | ver comandos do T03 |
| T04 | `RejectInvalidUtf8` (query, formulário e JSON → 400); path pelo `ValidatePathEncoding` do Laravel | ver comandos do T04 |
| T05 | JUDGE; disco `local` sem `serve` (FIND-037); regra de cobertura dos limites em toda rota; cota compartilhada API × admin provada; relatório, ADR Accepted | ver comandos do T05 |

## Camadas na ordem da requisição

```text
grupo api / web
  LimitRequestBodySize      corpo > 64 KB → 413
  RejectInvalidUtf8         query, formulário ou JSON fora do UTF-8 → 400
  (Laravel) ValidatePathEncoding, global: path malformado → 400
login (API, admin, confirmação de senha)
  VerifyLoginCredentials    3 contadores, incremento antes do bcrypt → 429
                            guard->validate() + Timebox → resposta uniforme
  PersonalAccessTokenPolicy só no login da API: conta privilegiada → 403
rotas autenticadas
  auth / auth:sanctum → token.ability → throttle:authenticated (120/min por usuário)
```

## Decisões fixadas na execução

| Decisão | Valor | Onde |
|---|---|---|
| Contadores de login | identidade + IP 5 / 30 min (zera no sucesso); identidade 20 / h; IP 100 / h | ADR-0013 itens 3 a 6 |
| Conta privilegiada | `AdminNavigation::allows()`, sem flag | item 10 |
| Token no admin | 1, 8 ou 24 h (padrão 8); exibido uma vez, na resposta, `no-store` | item 13 |
| Reconfirmação | 5 minutos, só nas rotas de criação de token | item 14 |
| Token derivado | limitado à validade efetiva da origem; `expires_at` no 201 | item 15 |
| Corpo | 64 KB nos grupos `api` e `web`; nenhuma exceção | itens 22 a 24 |
| UTF-8 | 400; o path fica com o `ValidatePathEncoding` do framework | item 26 |
| Tetos | `profile_ids` 50, `abilities` 3, senha e e-mail 255 | item 27 |

## Mudanças de contrato da API

- Conta inativa recebe `Invalid credentials.` (antes `Account is inactive.`), T02.
- Conta privilegiada recebe 403 no login e no `POST /tokens`, T06.
- `POST /tokens` devolve `expires_at`, que pode ser menor que o pedido, T06.
- `GET /tokens` é paginado (`data`, `links`, `meta`), T03.
- Novas respostas: 413 (corpo), 400 (UTF-8) e 429 com `Retry-After` (login e
  rotas autenticadas).

## Tests e mutações

| Arquivo | Testes |
|---|---|
| `Feature/Security/LoginThrottleTest.php` | 15 |
| `Feature/Security/PrivilegedTokenBoundaryTest.php` | 8 |
| `Feature/Admin/TokenManagementTest.php` | 13 |
| `Feature/Security/InputLimitsTest.php` | 10 |
| `Feature/Security/MalformedInputTest.php` | 7 |
| `Feature/Security/RouteCoverageTest.php` | +1 (limites de entrada em toda rota) e listas de autoatendimento |

Total: **682 testes** (3.659 assertions), sobre os 628 do P2.

Testes antigos ajustados sem perder cobertura:

- os que faziam login na API como `admin` passaram a usar uma conta não
  privilegiada;
- os que usavam `actingAs()` em rotas de token passaram a usar token real;
- as senhas desses testes passaram a ser geradas por teste, com
  `Str::password()`, por causa do alerta de senha exposta do GitHub.

**27 mutações**, todas derrubando ao menos um teste e todas revertidas:

| Task | Mutações |
|---|---|
| T02 (5) | e-mail sem normalizar; zerar o contador da identidade no sucesso; aceitar conta inativa; ler antes de incrementar; cota de IP frouxa |
| T06 (8) | login sem Policy; `POST /tokens` sem Policy; sem teto da origem; teto sem a expiração global; confirmação de 3 h; sem `password.confirm`; confirmação aceitando qualquer senha; sem `no-store` |
| T03 (7) | sem medir corpo sem `Content-Length`; limite fora por um; sem middleware na API; `profile_ids`, `abilities`, senha e e-mail sem teto |
| T04 (6) | sem query; sem formulário; sem JSON; sem recursão; ignorando chaves; fora do grupo `web` |
| T05 (1) | `'serve' => true` no disco `local` |

**Verificação com servidor real** (`php artisan serve` com PostgreSQL):

- **Concorrência (T02):** 20 requisições paralelas na mesma identidade
  resultaram em exatamente 5 × 422 e 15 × 429.
- **Entrada (T04):** `?search=%FF` passou de 500 a 400; `ação 🚀` responde
  200; um corpo de 70 KB recebe 413 com e sem `Content-Length`.
- Os dados de teste foram removidos do banco de desenvolvimento.

## Engineering Gate

| Componente | Precisa existir? | Já existia? | Laravel/PHP/PostgreSQL resolve? | Decisão |
|---|---|---|---|---|
| `VerifyLoginCredentials` | sim, um orçamento para três entradas de senha | um limiter por adapter | em parte: `RateLimiter::increment()` e `validate()` com Timebox | criada, só orquestra o framework |
| `TooManyLoginAttemptsException` | sim, `Retry-After` nas duas pontas | padrão `render()` do P1 | `ThrottleRequestsException` não atende o formulário | criada |
| Subclasse de `ThrottleRequests` ou lock próprio | não | — | `increment()` atômico | não criados |
| Hash fixo para e-mail inexistente | não | — | Timebox do guard | não criado |
| Limiter `authenticated` | sim, FIND-016 | não | `RateLimiter::for` | configuração |
| `config/trustedproxy.php` | sim, FIND-030 | não | fallback do `TrustProxies` | configuração |
| `PersonalAccessTokenPolicy` | sim, itens 11 e 12 | regra em `AdminNavigation` | Gate/Policy | criada |
| `IssueAccessToken` / `RevokeAccessToken` | sim, dois adapters | no controller | não | extraídas |
| Confirmação de senha | sim, item 14 | não | `password.confirm`, `passwordConfirmed()` | rota e tela finas |
| Flag `is_privileged` | não | `AdminNavigation::allows` | — | não criada |
| `LimitRequestBodySize` | sim, item 23 | não | `ValidatePostSize` só lê `Content-Length` contra o php.ini | criado; reusa `PostTooLargeException` |
| `RejectInvalidUtf8` | sim, FIND-026 | não | só o path (`ValidatePathEncoding`) | criado sem conferir o path |
| `PersonalAccessTokenResource` | sim, envelope de paginação | tokens serializados à mão | API Resource | criado |
| Teto de tokens por usuário | não agora | — | — | adiado (FIND-032) |
| Rotas `storage/{path}` | não | esqueleto do Laravel | `'serve' => false` | desligadas (FIND-037) |
| Tabela, coluna ou pacote novo | não | — | — | nada criado |

Pergunta do Security Lab ("precisa ser vulnerável de verdade?"): não se
aplica. Nenhum código vulnerável foi criado.

## Abstraction Review

**1. O que se repetiu:**

| Repetição | Ocorrências |
|---|---|
| Exceção de domínio com `render()` que responde JSON na API e volta ao formulário no admin | `LastActiveAdministratorException`, `TooManyLoginAttemptsException` e as de lifecycle do P2 |
| Lista de rotas de autoatendimento no `RouteCoverageTest` | API e admin |
| Middleware de entrada nos dois grupos | resolvido na própria fase com um array único em `bootstrap/app.php` |
| Teste que cria um usuário com senha gerada e devolve os dois | cerca de 25 testes do T02, T06 e T03 |
| Conferir a ability de token contra o catálogo | `TokenStoreRequest` e `StoreTokenRequest` do admin |

**2. O que merece abstração** (na próxima fase que tocar essas áreas):

- **Helper de teste** `userWithPassword()` em `tests/Pest.php`, que devolve
  o usuário e a senha gerada. É o maior volume de repetição, e os testes
  que ainda usam senha literal migrariam para ele.
- **Regras de token** compartilhadas pelas duas requests (`abilities` com
  teto e catálogo), como o admin já faz ao estender requests da API.

**3. O que continua específico:**

- As exceções de domínio: as mensagens e o destino do redirect fazem parte
  do contrato de cada caso.
- As listas de autoatendimento: API e admin têm superfícies diferentes, e a
  lista explícita é a própria documentação da regra.

## JUDGE

Critérios dos comentários do card T05 e tentativas de refutação, com
evidência:

| Contestação | Evidência | Veredito |
|---|---|---|
| 1. Existem dois orçamentos de login independentes | mesmas chaves nas duas pontas; `blocks the API login after failures on the admin form` e o inverso; a confirmação de senha também gasta a cota (`spends the login budget shared…`) | refutada |
| 2. Tentativas numa ponta não afetam a outra | testes cruzados acima, nos dois sentidos | refutada |
| 3. Rotacionar IP escapa | contador por identidade, independente do IP: `keeps limiting an account when the attacker rotates IPs` | refutada |
| 4. Admin troca senha por token na API e contorna controles do admin | 403 no login e no `POST /tokens`; token só no admin com senha de ≤ 5 min (`PrivilegedTokenBoundaryTest`, `Admin/TokenManagementTest`) | refutada; resta a promoção (riscos residuais) |
| 5. Sessão e Sanctum deveriam ser um só mecanismo | continuam distintos e compartilham só a proteção de credenciais e a cota por usuário (`shares one quota between the API and the admin session`) | mantém a separação |
| 6. Há primitive caseira de rate limit ou lock | só `RateLimiter::increment()`, `ThrottleRequests` e `Cache` do framework; mock estrito prova que a decisão vem do incremento | refutada; a corrida das rotas autenticadas segue aceita (item 20) |
| Existe outra entrada de senha fora da Action | busca por `Hash::check`, `attempt(`, `validate(`, `once(` e `auth.basic`: só `VerifyLoginCredentials` | refutada |
| Existe rota fora dos limites de entrada | `route:list` mostrou `GET`/`PUT /storage/{path}` fora dos grupos | **confirmada → FIND-037, corrigido**; regra nova no `RouteCoverageTest` |
| A cota "por usuário" é por adapter | teste novo: 120 requisições na API levam a sessão do admin a 429 | refutada |
| 64 KB quebra uso legítimo | maior corpo legítimo ≈ 20 KB; `lets a body of exactly 64 KB through` | mantém |
| Conferir o path no middleware próprio | o Laravel 13 já confere (`ValidatePathEncoding`), descoberto pelo teste escrito antes da implementação | removido do middleware |
| Senha longa ainda custa bcrypt ou contador | `refuses a password over 255 characters before checking it`, com spy no `RateLimiter` | refutada |
| Lockout provocado de uma conta | ~20 req/h de duas origens mantêm a conta bloqueada | aceito (decisão do usuário), registrado no ADR |
| Demonstração cria admin com senha conhecida | `UserSeeder` sem guarda de ambiente | **confirmada → FIND-038**, fora do gate; resolvido em 2026-11-01 |

Gate adicional do card: **não** há forma demonstrável de aumentar o
orçamento alternando admin e API, nem de contornar os controles
privilegiados por uma segunda superfície de login.

## Riscos residuais

- Lockout provocado: um atacante que conhece o e-mail e usa duas ou mais
  origens mantém a conta bloqueada com ~20 requisições por hora, até existir
  MFA. Sessões e tokens já emitidos continuam valendo.
- Corrida do `throttle` nas rotas autenticadas: um usuário passa da cota só
  com as próprias requisições simultâneas (item 20).
- Conta comum promovida a privilegiada mantém os tokens de API que já tinha
  até expirarem (≤ 7 dias). O mesmo vale para os tokens de login emitidos a
  contas privilegiadas antes do T06. O banco de desenvolvimento não tinha
  tokens, então não houve revogação manual.
- A definição de privilegiada depende da flag do Security Lab: uma conta só
  com `security-lab.*` é privilegiada com a flag ligada e comum com ela
  desligada.
- Corpo multipart em chunks depende do `post_max_size` e do limite do
  servidor web (item 25).
- Headers e nomes de arquivo não são conferidos quanto a UTF-8; nenhum é
  persistido hoje.
- Teto de tokens ativos por usuário adiado (FIND-032).
- Deploy: `TRUSTED_PROXIES` atrás de balanceador e o scheduler para
  `sanctum:prune-expired` são obrigatórios.

## Findings

- **RESOLVED no P2.5:** FIND-016, 018, 026, 027, 028, 029, 030, 031, 033,
  034, 035, 036 e 037.
- **RESOLVED (parcial):** FIND-032 (paginação; o teto de tokens fica
  `DEFERRED`).
- **Novos no JUDGE:** FIND-037 (resolvido) e FIND-038 (resolvido em
  2026-11-01: seeder de demonstração só em `local` e `testing`).
- **OPEN fora do P2.5:** FIND-008 e 024 (hardening).

Detalhes em [findings](../../findings/README.md).
