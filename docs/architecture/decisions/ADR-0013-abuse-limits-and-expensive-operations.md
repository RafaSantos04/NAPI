# ADR-0013 — Limites de abuso da plataforma e política de operações caras

[← ADRs](README.md) · [Roadmap](../../product/roadmap.md) · [Findings](../../findings/README.md)

## Status

Proposed (P2.5-T01). Decisões aprovadas pelo usuário em 2026-10-30, incluindo
a revisão Admin × API do mesmo dia. Passa a Accepted no JUDGE do P2.5-T05,
como o ADR-0012 no P2. Implementado até aqui: proteção de credenciais e
rotas autenticadas (T02) e conta privilegiada (T06).

## Context

O P3 vai receber o primeiro upload de tamanho arbitrário (especificação
OpenAPI) e o primeiro processamento cujo custo cresce com a entrada do
usuário. Antes disso, o roadmap exige fechar os controles básicos de abuso:
o P3 não começa com o FIND-016 aberto.

A análise do P2.5 encontrou mais do que o FIND-016 descrevia:

- o login da API limita por `e-mail + IP`, e a chave diferencia maiúsculas,
  enquanto `users.email` é `citext` (FIND-027, confirmado em execução);
- o login do admin tem a mesma chave por `e-mail + IP`, e a janela é de 60
  segundos porque o `hit()` não recebe decay: 5 falhas por minuto, contra 6
  a cada 30 minutos na API (FIND-016);
- o `ThrottleRequests` do Laravel lê o contador e só depois incrementa, e o
  login do admin registra a falha só depois do bcrypt: requisições
  simultâneas passam juntas (FIND-028);
- o 429 do login sai sem `Retry-After` (FIND-029);
- `TrustProxies` não está configurado, então todo limite por IP depende do
  deploy (FIND-030);
- nenhuma rota autenticada tem limite, e o tamanho do corpo só é limitado
  pelo `post_max_size` do PHP;
- listas sem `max:` fazem uma consulta ou comparação por item (FIND-031), e
  `GET /tokens` não pagina (FIND-032);
- entrada que não é UTF-8 válido vira 500 em formulários e em query strings
  da API (FIND-026);
- o login da API ainda revela e-mails cadastrados e contas inativas
  (FIND-018).

A revisão Admin × API (comentários dos cards T01, T02 e T05, 2026-10-30)
acrescentou:

- os dois logins conferem a mesma senha com contadores independentes;
  alternando entre eles, um IP chega a ~312 tentativas por hora contra uma
  conta (FIND-033);
- não há contador por identidade: trocando de IP, as tentativas contra uma
  conta são ilimitadas (FIND-034);
- uma conta privilegiada troca a senha, na API, por um token de 7 dias com
  todas as abilities, enquanto a sessão do admin dura 120 minutos; qualquer
  controle futuro do admin teria um desvio (FIND-035);
- `POST /tokens` limita as abilities do token derivado, mas não a validade
  (FIND-036).

A diretriz da revisão: centralizar a **proteção de credenciais**, não o
mecanismo de autenticação; um orçamento único para a mesma identidade; e o
que o Laravel já oferece antes de código próprio.

Regra herdada do quadro: nenhum limiter sem rota consumidora. Este ADR fixa
a política de operações caras, mas o código dela entra com a rota do P3.

## Decision

### Proteção de credenciais

1. **Um único ponto confere senha.** Uma Action do domínio IAM
   (`app/Domain/IAM/Actions`, nome fixado no T02) é chamada por toda rota
   que confere senha: `POST /api/v1/auth/login`, `POST /admin/login` e a
   confirmação de senha do admin (item 14). Ela normaliza a identidade,
   reserva a tentativa, confere a senha e devolve o usuário. Cada adapter
   mantém o seu mecanismo: o admin abre a sessão do guard `web`, e a API
   emite o token Sanctum (ADR-0009). Sessão e Sanctum não se fundem.
2. **Identidade normalizada antes de qualquer chave:** `mb_strtolower` do
   e-mail, o mesmo critério da comparação `citext`; o `TrimStrings` global
   já remove os espaços. Sem `Str::transliterate`, que juntaria e-mails
   distintos num mesmo contador. Fecha o FIND-027.
3. **Três contadores cumulativos**, com as mesmas chaves em todas as rotas
   do item 1, sem prefixo por adapter:

   | Contador | Chave | Cota | Zera no sucesso | Contra o quê |
   |---|---|---|---|---|
   | identidade + IP | e-mail normalizado + IP | 5 a cada 30 min | sim | rajada de uma origem contra uma conta |
   | identidade | e-mail normalizado | 20 por hora | não | ataque distribuído, com rotação de IP |
   | IP | IP | 100 por hora | não | spraying e credential stuffing de uma origem |

   Um IP sozinho faz no máximo 10 tentativas por hora contra uma conta,
   metade da cota da identidade: para bloquear uma conta é preciso mais de
   uma origem. O contador existe para qualquer e-mail, cadastrado ou não,
   então o bloqueio não revela cadastro. Fecha o FIND-016 no login e os
   FIND-033 e FIND-034.
4. **Incrementar antes do bcrypt e decidir pelo valor devolvido** por
   `RateLimiter::increment()`. Esse é o padrão que a documentação do
   Laravel 13 indica para requisições simultâneas, e o incremento é atômico
   no cache `database` (`lockForUpdate` dentro de transação). Duas
   tentativas no mesmo milissegundo recebem contadores diferentes, e só as
   que couberem na cota chegam ao hash. Sem lock próprio e sem subclasse do
   framework. Fecha o FIND-028 no login.
5. **No sucesso, só o contador identidade + IP zera.** O da identidade não
   zera; senão cada login do dono devolveria cota a quem ataca de vários
   IPs. O do IP não zera; senão quem tem uma conta válida limparia o próprio
   IP entre tentativas contra outras contas.
6. Tentativa recusada também conta, e a janela não se estende: o timer é
   gravado só no primeiro incremento. A janela é fixa, então na virada um
   cliente pode fazer até 2× a cota em pouco tempo; o objetivo é limitar o
   volume sustentado, e isso é aceito.
7. **A senha é conferida por `Auth::guard('web')->validate()`**, com
   `is_active` nas credenciais. O guard já roda dentro de um `Timebox` de
   200 ms: e-mail inexistente, conta inativa e senha errada levam o mesmo
   tempo e recebem a mesma resposta. O login da API remove `exists:users` e
   responde `Invalid credentials.` também para conta inativa. **Muda o
   contrato**: `Account is inactive.` deixa de existir. Fecha o FIND-018 sem
   hash fixo próprio.
8. **Recusa por limite:** o evento `Lockout` do Laravel é disparado. A API
   responde 429 com `Retry-After` (o maior tempo restante entre os
   contadores estourados) e a mensagem do login; o admin mostra o erro no
   formulário com os segundos, como hoje. Fecha o FIND-029.
9. **Custo aceito: bloqueio provocado.** Quem conhece um e-mail e usa duas
   ou mais origens mantém a conta bloqueada com ~20 requisições por hora.
   Sessões e tokens já emitidos continuam valendo. A defesa definitiva é o
   MFA; quando existir, ele entra nesta mesma Action e vale para todas as
   entradas.

### Conta privilegiada

10. **Privilegiada é a conta que entra na área admin**, pela regra que já
    existe: `AdminNavigation::allows()`, que pergunta às Policies se alguma
    seção é visível (ADR-0008). Sem flag nem lista nova.
11. **A senha de conta privilegiada só entra pelo `/admin/login`.** O login
    da API confere a senha pela Action do item 1, consumindo os contadores,
    e responde 403 sem token para conta privilegiada. Saber que a senha está
    certa não dá nada novo: a mesma senha abre o `/admin`, que tem os mesmos
    contadores. Fecha parte do FIND-035.
12. **`POST /api/v1/tokens` também recusa conta privilegiada**; senão um
    token emitido no admin geraria outros fora da reautenticação. As duas
    recusas ficam numa Policy de emissão de token, não em checagem no
    controller (ADR-0008).
13. **O token de conta privilegiada nasce na sessão do admin** (P2.5-T06):
    tela dos tokens do próprio usuário, para listar, criar e revogar.
    Abilities escolhidas do catálogo e expiração curta: 1, 8 ou 24 horas,
    padrão 8 (fixado no T06). O token aparece uma única vez, na própria
    resposta do POST, com `Cache-Control: no-store`: um redirect com flash
    gravaria o texto puro na tabela `sessions`. A emissão e a revogação são
    auditadas pelos eventos `TokenCreated` e `TokenRevoked`, que já
    existem. O link da tela fica no cabeçalho, fora das seções do
    `AdminNavigation`, porque essas seções definem quem é privilegiado.
14. **Criar token exige reautenticação recente**, pelo middleware
    `password.confirm` do Laravel: `RequirePassword::using()` com 5 minutos
    nas rotas de criação, em vez das 3 horas do `password_timeout`. A
    rota de confirmação confere a senha pela Action do item 1, com os
    mesmos contadores, e marca a sessão com `passwordConfirmed()`.
15. **O token derivado não vive mais que o de origem**, para qualquer
    conta: em `POST /tokens`, o `expires_at` fica limitado à validade
    efetiva do token que pede. A validade efetiva é o `expires_at` dele ou
    o `created_at` mais a expiração global, o que vier antes, porque o
    token de login nasce sem `expires_at`. A validade reduzida volta em
    `expires_at` no 201. Fecha o FIND-036.
16. **A emissão vira uma Action** (`IssueAccessToken`), com `createToken`,
    o teto e o evento numa transação, antes no controller. Ela carrega
    regra de negócio e passa a ter dois adapters, API e admin (ADR-0007,
    ADR-0009). A revogação também vira Action (`RevokeAccessToken`), pelo
    mesmo motivo. A recusa na API entra junto com a tela do admin, para que
    a API de IAM nunca fique sem forma de autenticar. Fecha o FIND-035.
17. **`sanctum:prune-expired --hours=24` agendado diariamente** em
    `routes/console.php`, como indica a documentação do Sanctum. O deploy
    precisa rodar o scheduler.
18. Os tokens de login já emitidos para contas privilegiadas expiram no
    prazo global, em até 7 dias depois do deploy do T06. O relatório da
    fase registra se houve revogação manual antes disso.

### Rotas autenticadas

19. **Limiter `authenticated`**: `Limit::perMinute(120)->by()` com o id do
    usuário, aplicado por `throttle:authenticated` no grupo `auth:sanctum`
    da API e no grupo `auth` do admin. A chave é o usuário, não o token, já
    que um usuário pode criar tokens novos e cada um teria cota própria.
    Requisições sem autenticação válida recebem 401 antes do limiter. A
    resposta é o 429 padrão, com `Retry-After` e `X-RateLimit-*`.
20. **Nessas rotas, a corrida do `ThrottleRequests` é aceita.** Ele lê e
    depois incrementa, então a ultrapassagem se limita às requisições
    simultâneas de um usuário já autenticado, que não é superfície de força
    bruta. Redis com `ThrottleRequestsWithRedis` fica para o P14, se o
    volume pedir. Fecha o FIND-016 nas rotas autenticadas.

### Proxies confiáveis

21. **`config/trustedproxy.php`**, com `'proxies' => env('TRUSTED_PROXIES')`,
    lido pelo fallback do próprio `TrustProxies` do Laravel
    (`config('trustedproxy.proxies')`). Não se usa `trustProxies(at:
    env(...))` no `bootstrap/app.php`: o método não aceita closure, e com
    `config:cache` o `env()` fora dos arquivos de config volta nulo. Sem a
    variável, nenhum proxy é confiável, exceto em hosts do Laravel Cloud,
    Forge e Vapor, onde o próprio middleware confia no balanceador da
    plataforma. Nunca `*` configurado à mão. O documento de deploy registra
    a variável. Fecha o FIND-030.

### Tamanho do corpo

22. **Limite padrão de 64 KB** por um middleware nos grupos `api` e `web`,
    respondendo 413 com a `PostTooLargeException` do Laravel (JSON na API,
    página no admin). O maior corpo legítimo de hoje fica perto de 20 KB
    (`authorized_scope` com 5000 caracteres, matriz de 200 menus).
23. O middleware confere o `Content-Length` quando ele existe e o tamanho
    real do corpo quando não existe (envio em chunks, que o
    `ValidatePostSize` não pega).
24. **Exceção só por rota consumidora.** Nenhuma exceção existe no P2.5. A
    primeira é a rota de import do P3, que escolhe o mecanismo (parâmetro do
    middleware ou substituição na rota) junto com o próprio limite.
25. `post_max_size`, `upload_max_filesize` e o `client_max_body_size` do
    servidor web são defesa em profundidade, documentados para o deploy.

### Entrada

26. **UTF-8 inválido é 400**, por um middleware nos grupos `api` e `web`
    que roda antes da validação e do route binding. Ele confere o path, as
    chaves e os valores da query e do corpo de formulário (recursivamente)
    e o corpo bruto quando ele é JSON. Arquivos enviados ficam de fora. A
    requisição está malformada, não inválida, por isso 400 e não 422.
    Fecha o FIND-026.
27. **Listas com teto do tamanho do domínio:** `abilities` até o número de
    abilities do catálogo (3), `profile_ids` até 50. **Senhas** com
    `max:255` no login da API, no login do admin, na confirmação de senha e
    na criação de usuário. O bcrypt só usa os primeiros 72 bytes; isso fica
    registrado, sem regra por bytes. Fecha o FIND-031.
28. **`GET /api/v1/tokens` pagina** com 15 itens, no envelope das demais
    listagens. O teto de tokens ativos por usuário fica adiado (FIND-032
    parcial).

### Operações caras (aplicada a partir do P3)

Operação cara é aquela cujo custo cresce com a entrada do usuário: import
de especificação, geração de relatório e, no futuro, checks automatizados.

29. **Teto antes do trabalho.** Primeiro o tamanho em bytes (exceção de
    rota do item 24). Depois, durante o processamento, tetos de domínio
    (quantidade de operações, profundidade, expansão de `$ref`). Estourar
    um teto é 422 com mensagem própria, sem stack trace nem caminho de
    arquivo.
30. **Síncrono por padrão**, com tetos dimensionados para um orçamento de
    tempo medido com amostras reais. A fila só entra quando a medição
    estourar o orçamento. Nesse caso, a operação ganha um registro com
    status (`pending`, `processing`, `failed`, `completed`), timeout e
    `tries` explícitos no job, e retry só se a operação for idempotente.
31. **Uma operação do mesmo tipo por vez em cada organização**, com
    `Cache::lock` sem espera. Uma segunda chamada recebe 409 enquanto a
    primeira não termina.
32. **Cota por organização**, além da do usuário, com o número fixado pela
    fase consumidora.
33. **Idempotência pelo conteúdo** (sha256) quando o resultado é
    determinístico: reenviar o mesmo arquivo devolve o resultado existente.
    Header `Idempotency-Key` só quando surgir uma operação não
    determinística.
34. **Sem rede.** Nenhuma operação cara busca recurso remoto (`$ref`
    remoto, URL de especificação) antes do ADR de egress do P10.
35. **Falha sem estado parcial:** transação no síncrono, registro com
    status na fila.

Os números do P3 (bytes, operações, `$ref`, orçamento de tempo, cota por
organização) são fixados no ADR do P3.

## Rationale

- **Laravel resolve quase tudo**: `RateLimiter::increment()` atômico,
  `Timebox` no `validate()` do guard, evento `Lockout`, `password.confirm`
  com timeout por rota, expiração por token no Sanctum,
  `sanctum:prune-expired`, `PostTooLargeException`, `TrustProxies` com
  fallback em config, vários `Limit` por limiter. O código novo é uma
  Action de credenciais, uma Action de emissão de token, uma Policy de
  emissão, dois middlewares pequenos (corpo e UTF-8) e a tela de tokens do
  admin. Cada um responde a um finding.
- **Centralizar a proteção, não o mecanismo**: sessão e Sanctum servem
  consumidores diferentes (ADR-0009), mas a senha é a mesma, então o
  orçamento é um só. A regra de "quem pode trocar senha por credencial"
  fica no domínio, não espalhada pelos adapters.
- **Três contadores** porque cada um cobre um ataque que os outros deixam
  passar; as cotas impedem que uma única origem bloqueie uma conta.
- **A conta privilegiada tem uma única entrada por senha**, a mais curta e
  a única onde controles adicionais vão morar. O token dela nasce de uma
  sessão já autenticada e reautenticada, com o mínimo de poder e de tempo.
- 64 KB fica três vezes acima do maior corpo legítimo, e abaixo do que torna
  a validação item a item cara.

## Alternatives Considered

- **Subclasse do `ThrottleRequests` que incrementa primeiro** (versão
  inicial deste ADR): descartada. O framework já oferece o incremento
  atômico; no login, ele é usado diretamente, e nas rotas autenticadas a
  corrida é aceita.
- **`throttle:login` como middleware nas duas rotas de login:** descartado.
  O middleware lê e depois incrementa, e roda antes da normalização e da
  confirmação de senha do admin, que não é uma rota de login.
- **Contadores separados por adapter:** descartado; soma as cotas
  (FIND-033).
- **Só `e-mail + IP` e IP:** descartado; não limita o ataque distribuído
  contra uma conta (FIND-034).
- **Atraso progressivo em vez de bloqueio:** descartado por ora; evita o
  bloqueio provocado, mas pede estado e cálculo próprios sem um caso que
  justifique.
- **Hash fixo próprio para e-mail inexistente:** descartado; o `Timebox` do
  guard já iguala o tempo.
- **Manter senha → token na API para conta privilegiada, com justificativa:**
  descartado; a API entrega a credencial mais forte e seria o desvio de
  qualquer controle futuro do admin (FIND-035).
- **Recusar a conta privilegiada na API sem a tela de emissão no admin:**
  descartado; a API de IAM ficaria sem forma de autenticar.
- **Flag `is_admin` para marcar conta privilegiada:** descartado pelo
  ADR-0008; a regra de acesso à área admin já responde.
- **Redis com `ThrottleRequestsWithRedis`:** atômico, mas exige
  infraestrutura que o projeto ainda não tem. Fica para o P14.
- **Limite por token nas rotas autenticadas:** descartado; contornável
  criando tokens.
- **Limite de corpo só no php.ini ou no nginx:** descartado como controle
  principal; é um valor único para todas as rotas, o P3 precisa de MBs numa
  só, e não pode ser testado no Pest. Fica como defesa em profundidade.
- **Regra de validação UTF-8 por campo:** descartada; teria de ser lembrada
  em todo FormRequest, e a query string do FIND-026 nem passa por um.
- **422 para UTF-8 inválido:** descartado; não há campo inválido para
  apontar, a requisição inteira está malformada.
- **Fila desde o primeiro import:** adiada; exige registro de status, worker
  e tratamento de falha antes de existir uma medição que os justifique.
- **Teto de tokens ativos agora:** adiado por decisão do usuário; a
  paginação resolve a listagem e o limiter por usuário resolve a cota.

## Consequences

### Positive

- Brute force, credential stuffing e ataque distribuído contra uma conta
  passam a ter teto, inclusive em rajada e alternando entre admin e API.
- A conta privilegiada tem uma única entrada por senha, e o MFA futuro tem
  um só lugar para entrar.
- Entrada malformada ou grande demais recebe 4xx em vez de 500 ou de
  processamento caro.
- O P3 já começa com a política de operações caras decidida.

### Negative

- **Bloqueio provocado**: quem conhece um e-mail pode manter a conta
  bloqueada, inclusive a de um administrador, até existir MFA.
- **Contrato muda**: conta inativa recebe `Invalid credentials.`; conta
  privilegiada recebe 403 no login da API e no `POST /tokens`, e passa a
  gerar o token no admin.
- Usuários atrás do mesmo NAT dividem 100 tentativas de login por hora.
- O deploy passa a depender do scheduler (`sanctum:prune-expired`) e de
  `TRUSTED_PROXIES` atrás de proxy.
- Toda resposta de senha errada leva pelo menos 200 ms (`Timebox`).

## Security Impact

Fecha FIND-016, 018, 026 a 031 e 033 a 036, e parcialmente o 032. O FIND-028
fecha pelo login, com a corrida das rotas autenticadas aceita (item 20).

Testes obrigatórios do T02 e do T06, também exigidos pelo JUDGE do T05:

- tentativas no `/admin/login` bloqueiam o login da API para a mesma
  identidade, e o inverso;
- trocar de IP não escapa do contador da identidade;
- trocar de e-mail no mesmo IP não escapa do contador do IP;
- variações de maiúsculas e espaços do mesmo e-mail não geram cotas
  distintas;
- a decisão usa o valor devolvido pelo incremento: com o `RateLimiter`
  devolvendo um valor acima da cota, a senha nem é conferida;
- e-mail inexistente, conta inativa e senha errada recebem a mesma
  resposta;
- conta privilegiada não obtém token pelo login da API nem pelo
  `POST /tokens`, e obtém pelo admin só com a senha reconfirmada;
- token derivado não ultrapassa a validade do token de origem.

A concorrência real não é testável no Pest com `RefreshDatabase`, mesmo
limite do threat model para locks. Além do teste acima, ela é verificada
manualmente com requisições paralelas contra o PostgreSQL, e o resultado vai
para o relatório da fase.

## References

- [Findings](../../findings/README.md): FIND-016, 018, 026 a 036
- [ADR-0007](ADR-0007-dto-action-boundary.md): DTO + Action para casos de uso com regra
- [ADR-0008](ADR-0008-layered-authorization-model.md): autorização em camadas
- [ADR-0009](ADR-0009-web-session-and-bearer-adapters.md): adapters web e Bearer
- [Threat model](../../security/threat-model.md)
- [Roadmap, P2.5 e P3](../../product/roadmap.md)
- Documentação do Laravel 13: Rate Limiting (incremento atômico), Authentication (password confirmation), Requests (trusted proxies), Sanctum (token expiration)
