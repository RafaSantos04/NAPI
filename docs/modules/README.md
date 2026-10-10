# Módulos

[← Documentação](../README.md)

| Módulo | Estado | Documento |
|---|---|---|
| IAM: usuários, perfis, menus, permissões, tokens, auditoria | Implementado como API REST v1 | [iam.md](iam.md) |
| Security Lab: laboratório controlado, teste IDOR/BOLA | Implementado na área administrativa (Fase 5.1), desligado por padrão | [security.md](security.md) |

A fronteira entre os módulos é conceitual: o código de IAM não fica sob um
namespace `Modules\IAM`. As Actions ficam em `app/Domain/IAM` e `app/Domain/Security`, e o restante
(models, controllers, policies) segue a estrutura padrão do Laravel. Veja
[project-structure.md](../architecture/project-structure.md).
