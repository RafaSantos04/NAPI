# Segurança

[← Documentação](../README.md)

Controles de segurança **existentes** no NAPI e seus limites.

| Documento | Responde |
|---|---|
| [authentication.md](authentication.md) | Como um ator prova quem é, e como essa prova expira ou é revogada |
| [authorization.md](authorization.md) | Os três portões de acesso, a matriz, as Policies e a diferença entre visibilidade de menu e autorização |
| [security-policies.md](security-policies.md) | Least privilege, deny by default e o catálogo de invariantes, com local de aplicação e teste |
| [audit.md](audit.md) | O que é auditado, como e com quais limitações |
| [threat-model.md](threat-model.md) | Ameaças por superfície, mitigação existente e risco residual |

Os problemas encontrados estão em [findings](../findings/README.md). Nenhum
controle é declarado aqui como mitigado sem evidência no código ou em teste.
