# ADR-0010 — Security Lab controlado, com recursos sintéticos

[← ADRs](README.md) · [Módulo Security Lab](../../modules/security.md) · [IDOR / BOLA](../../security/idor.md) · [Threat model](../../security/threat-model.md#security-lab)

## Status

Accepted (Fase 5.1). Confirma as fronteiras propostas para o módulo na
Fase 3.1.

## Context

O NAPI tem duas áreas: IAM e Segurança. A segunda precisa demonstrar falhas e
controles de verdade, o que significa manter no repositório **código
deliberadamente inseguro**. A Fase 5.1 implementa o primeiro caso, IDOR/BOLA,
e precisava decidir onde esse código vive e o que o impede de sair do
laboratório.

A Fase 3.1 já tinha proposto que o laboratório usasse o IAM para acesso, não
alterasse dados reais e auditasse suas execuções, "a serem confirmadas em ADR
quando a fase começar".

## Decision

1. **O comportamento vulnerável existe só dentro de um caso de uso.** Cada
   teste é uma Action (`RunIdorTest`) que executa a operação insegura no
   processo e registra o resultado. Não há rota que sirva um recurso sem a
   verificação: nenhuma rota do laboratório recebe identificador de recurso.
2. **Alvos e identidades simuladas são sintéticos.** O cenário vulnerável só
   lê `security_lab_resources`. As personas (actors) são usuários sintéticos,
   inativos e sem perfil. `users`, `profiles`, tokens e `audit_logs` nunca são
   alvo.
3. **Feature flag, desligada por padrão.** `config('security.lab.enabled')`,
   de `SECURITY_LAB_ENABLED`. Desligado: as rotas respondem 404 (middleware
   `security.lab`), a seção some da navegação, o caso de uso recusa a
   execução e o seeder não cria personas.
4. **Acesso pelo IAM existente.** Uma área de permissão `security-lab` na
   matriz: `view` abre o laboratório e o histórico, `create` executa um teste.
   Sem ação nova na matriz, sem checagem por slug de perfil.
5. **Operator e actor são identidades separadas.** O actor é contexto do
   teste, avaliado com `Gate::forUser()`. A sessão nunca muda de dono.
6. **Execução e auditoria são registros diferentes.** `security_test_runs`
   guarda o que o teste observou; `audit_logs` guarda que um operator o
   executou. São gravados na mesma transação.
7. **Três fatos por execução**: status da execução, resposta observada e
   veredito de segurança. Um erro técnico é sempre inconclusivo.
8. **Sem framework de testes ainda.** Nada de interface, classe base,
   registro ou pipeline até existir um segundo teste concreto que mostre o
   ponto de extensão real.

## Rationale

- **Caso de uso em vez de endpoint.** Um endpoint vulnerável é uma falha real
  cuja segurança depende de todos os controles ao redor continuarem certos
  para sempre. Dentro de uma Action, a operação insegura não tem endereço: só
  roda quando um operator autorizado pede, e sempre deixa registro.
- **Dados sintéticos.** O cenário vulnerável devolve conteúdo de verdade. Com
  dados sintéticos, o pior resultado de uma falha do próprio laboratório é
  expor um texto fictício.
- **FK em vez de alvo polimórfico.** `target_resource_id` referencia
  `security_lab_resources`. O banco garante que uma execução só aponta para
  recurso sintético, o que um par `target_type`/`target_id` não garantiria.
- **Flag conferida em tempo de requisição.** Registrar as rotas
  condicionalmente dependeria do cache de rotas e não seria testável por
  configuração. O middleware decide a cada requisição.
- **`create` como "executar".** Executar um teste cria uma execução. A matriz
  já expressa isso, e uma quinta ação mudaria o modelo de permissões do IAM
  por um único uso.
- **Personas em `users`.** O cenário protegido demonstra uma Policy real, que
  recebe um `User`. Uma tabela própria de personas exigiria um segundo tipo de
  identidade só para o laboratório.
- **Primeiro caso guia a abstração.** `security_test_runs` é genérica onde já
  se sabe que será (chave do teste, cenário, operator, actor, três fatos,
  `result_context` em JSONB). O resto fica específico até haver evidência.

## Alternatives Considered

- **Endpoints vulneráveis atrás de permissão.** Descartado: decisão 1.
- **Ambiente separado (outro deploy ou banco) para o laboratório.** Isola
  melhor, mas tira o laboratório do portfólio navegável e não demonstra a
  integração com o IAM. A flag desligada por padrão cobre o risco de um
  deploy descuidado.
- **Simular o actor com `Auth::login()`.** Trocaria a sessão do operator, com
  risco de fixação e de a auditoria apontar a pessoa errada. Descartado.
- **Tabela específica de IDOR, ou tabela de eventos por passo.** Nada no
  primeiro teste exige constraint, relação ou consulta que
  `security_test_runs` não atenda.
- **Ação `execute` na matriz.** Descartada: ver Rationale.
- **Recusar o laboratório em `APP_ENV=production`.** Impediria a demonstração
  hospedada do portfólio. A decisão fica explícita na variável de ambiente.

## Consequences

### Positive

- O código inseguro tem um único lugar, pequeno e revisável.
- Quatro barreiras independentes para o mesmo risco: flag, permissão, dados
  sintéticos e ausência de URL.
- Toda execução é rastreável ao operator real.
- Um segundo teste reutiliza tabela, enums, auditoria, flag e permissão.

### Negative

- As personas aparecem na administração de usuários, como contas inativas. Um
  administrador pode ativá-las ou excluí-las.
- `security_test_runs.target_resource_id` supõe alvo sintético desse tipo. Um
  teste com outro tipo de alvo vai exigir uma coluna nova ou outra modelagem.
- A permissão `create` significa "executar" nesta área, o que precisa ser
  lido na documentação.
- Ligar a flag em produção é uma decisão de quem opera; o código não impede.

## Security Impact

Introduz no repositório código que, fora do laboratório, seria uma falha. As
mitigações estão no [threat model](../../security/threat-model.md#security-lab)
e nas invariantes INV-21 a INV-23.

## References

- `config/security.php`, `app/Http/Middleware/EnsureSecurityLabEnabled.php`
- `app/Domain/Security/Actions/RunIdorTest.php`, `app/Domain/Security/Enums/*`
- `app/Models/{SecurityLabResource,SecurityTestRun}.php`, `app/Policies/{SecurityLabResourcePolicy,SecurityTestRunPolicy}.php`
- `database/migrations/2026_10_10_080103_create_security_lab_resources_table.php`, `2026_10_10_080105_create_security_test_runs_table.php`
- `database/seeders/{MenuSeeder,SecurityLabSeeder}.php`
- `tests/Feature/SecurityLab/*`, `tests/Feature/Security/RouteCoverageTest.php`
