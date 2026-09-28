# API

[← Documentação](../README.md)

- [v1.md](v1.md): inventário de todos os endpoints existentes, com as
  camadas que cada um atravessa (autenticação, matriz, Policy, Request,
  Controller, Action, Resource) e as respostas HTTP.

## Convenções

- Prefixo e versão: `/api/v1`, definido em `routes/api.php`.
- Autenticação: `Authorization: Bearer <token>`, exceto no login.
- Identificadores: ULID nos recursos de IAM; inteiro nos tokens.
- Erros: sempre JSON em `api/*` (`bootstrap/app.php`).
- Negação de acesso: 404 por padrão; veja
  [authorization.md](../security/authorization.md).

Ainda não há especificação OpenAPI. Este diretório documenta a arquitetura
da API, não um contrato formal.
