# Módulo Security Lab

[← Módulos](README.md) · [Threat model](../security/threat-model.md) · [Testes de segurança](../testing/security-tests.md)

## Estado atual

**Planejado. Não há código.** O README descreve a área como "testes e
ferramentas de segurança". Nenhum controller, rota, model ou teste pertence a
ela hoje.

Não confundir com a documentação em [`docs/security/`](../security/README.md),
que descreve os **controles de segurança do IAM**, já existentes. O Security
Lab será um módulo funcional separado.

## Propósito

Oferecer um espaço dentro do NAPI para exercitar e demonstrar técnicas de
segurança **contra o próprio sistema**, de forma controlada: verificar
controles de autenticação e autorização, validar findings e comprovar
mitigações.

## Relação com o IAM

```text
IAM                              Security Lab
─────────────────────────        ─────────────────────────────
implementa controles       ───▶  verifica os controles
(Sanctum, matriz, Policies,      (cenários de ataque, testes
 auditoria)                       de regressão, relatórios)
                           ◀───  consome o IAM para autenticar
                                 e autorizar quem usa o Lab
```

Fronteiras propostas, a serem confirmadas em ADR quando a fase começar:

- o Lab **usa** o IAM para autenticação e autorização (acesso restrito por
  perfil e matriz), sem mecanismo próprio;
- o Lab **não altera** dados de IAM de produção; cenários rodam contra dados
  de teste ou ambiente isolado;
- toda execução no Lab gera auditoria no mesmo `audit_logs`.

## Ponto de partida

O [threat model](../security/threat-model.md) e os
[findings](../findings/README.md) desta fase são o backlog natural do Lab:
cada ameaça com risco residual é um cenário de verificação candidato. Os
primeiros cenários concretos (token somente leitura executando escrita,
FIND-001; token de usuário desativado, FIND-003; lockout administrativo por
renomear um menu, FIND-005) foram corrigidos na Fase 3.2 e hoje existem como
testes de regressão em `tests/Feature/Security/`. Eles são o modelo natural de
um cenário do Lab: reproduzir, provar a correção e impedir a regressão.

## Fora de escopo nesta fase

Nenhuma ferramenta ofensiva foi ou será implementada na Fase 3.1.
