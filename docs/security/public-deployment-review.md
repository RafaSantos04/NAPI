# Revisão para exposição pública

[← Segurança](README.md) · [Threat model](threat-model.md) · [Deploy](../deployment-portainer.md)

**Resultado em 13/11/2026: GO condicionado.** O repositório tem uma imagem de
produção e não possui advisories conhecidos no lock após o hardening, mas a
publicação depende dos controles de infraestrutura abaixo. A captura do
Portainer não comprova firewall, TLS válido, backup nem portas externas; esses
itens só podem ser fechados na VM e por um teste de fora da rede.

## Evidência no repositório

| Controle | Evidência | Situação |
|---|---|---|
| Dependências | `composer audit --locked` após Laravel 13.42, CommonMark 2.10.7 e Symfony corrigido | sem advisories conhecidos |
| Segredos | `.env` ignorado e não rastreado; exemplos só têm placeholders | aprovado; rotacionar o segredo exposto na conversa |
| Conta inicial | demo recusada em produção; `napi:bootstrap-admin` idempotente e testado | aprovado |
| Banco | sem `ports`, rede Docker interna, SCRAM e volume dedicado | aprovado no manifesto; validar na VM |
| Porta da aplicação | `APP_PORT` publica 8006 para o NPM | restringir no firewall ao IP do NPM |
| Aplicação | `APP_DEBUG=false`, cookies secure/httpOnly, sessão criptografada, Lab desligado | aprovado no manifesto |
| Proxy | `TRUSTED_PROXIES` obrigatório e específico | depende do IP de origem visto pela aplicação |
| Runtime | filesystem read-only, `no-new-privileges`, limites de PID, healthchecks | aprovado no manifesto |
| Scheduler | `schedule:work` supervisionado junto do Apache | aprovado; necessário para poda do Sanctum |
| HTTP | headers de proteção, limite de 10 MB no Apache e limite de 64 KB nas rotas atuais | aprovado; CSP ainda aceita inline nas telas legadas |

## Condições antes de publicar

- Rotacionar a senha mostrada na conversa e nunca reutilizá-la no stack.
- Usar `APP_KEY`, senha de banco e senha inicial de admin diferentes, fortes e
  exclusivos desse ambiente.
- Deixar públicos somente 80/443; restringir a porta 8006 ao IP do NPM e
  restringir Portainer 9443, NPM admin 81 e SSH por firewall/VPN/allowlist. O aviso **Não seguro** visto no acesso por IP
  ao Portainer deve ser eliminado para qualquer acesso administrativo.
- Emitir certificado confiável no NPM, forçar HTTPS e testar cookies, redirect,
  host e IP real nos logs. Não usar `TRUSTED_PROXIES=*`.
- Manter `SECURITY_LAB_ENABLED=false` nesse ambiente.
- Restringir `/admin` por VPN ou allowlist enquanto MFA não existir. Se o admin
  permanecer público, registrar formalmente esse risco residual e monitorar
  lockouts e autenticações.
- Automatizar backup externo do PostgreSQL e executar ao menos uma restauração.
- Rodar build e smoke test na mesma arquitetura da VM antes da abertura.

## Riscos residuais conhecidos

- Não existe MFA para a conta administrativa.
- A CSP precisa de `'unsafe-inline'` enquanto duas telas Blade mantiverem
  scripts inline e a árvore usar style inline.
- FIND-008 permanece bloqueador antes de qualquer uso de CPF; a superfície
  atual não lê nem exibe esse dado.
- Parcelas de auditoria e retenção continuam documentadas no threat model.
- Administradores do Portainer/Docker conseguem ler variáveis do contêiner;
  remova o segredo de bootstrap após o primeiro login e limite esse papel.

Com todas as condições fechadas, a landing pode ser pública atrás do NPM. A
área administrativa deve ser tratada como backoffice privilegiado, não como
parte pública da divulgação.
