# Deploy com Docker Compose, Portainer e Nginx Proxy Manager

[← Documentação](README.md) · [Revisão de segurança](security/public-deployment-review.md)

O `compose.yaml` mantém o stack em dois serviços: `app` e `db`. O PostgreSQL
fica somente na rede `internal`; `app` também entra na rede externa do Nginx
Proxy Manager (NPM), sem publicar porta no host. O NPM deve encaminhar para
`http://napi-app:80` pela rede compartilhada.

## Pré-requisitos

1. DNS do domínio apontando para a VM.
2. Somente `80/tcp` e `443/tcp` públicos. Restrinja `81` (NPM admin), `9443`
   (Portainer) e SSH a VPN ou IPs administrativos. Nunca publique `5432`.
3. No Portainer, confirme em **Networks** o nome e o CIDR da rede do NPM. A
   captura sugere uma rede do stack `npm`, mas nome e subnet devem ser lidos
   da VM, não presumidos.
4. Gere valores únicos para `APP_KEY`, senha do banco e senha inicial do
   administrador. A senha que apareceu na conversa deve ser considerada
   comprometida e não pode ser reutilizada.

O modelo de variáveis está em `docker/stack.env.example`. O arquivo preenchido
fica fora do Git; no Portainer, prefira cadastrar os valores no ambiente do
stack. `TRUSTED_PROXIES` recebe somente o CIDR real da rede do NPM — nunca `*`.

## Subida pelo Portainer

1. Em **Stacks**, crie um stack a partir deste repositório e selecione
   `compose.yaml`.
2. Cadastre todas as variáveis do modelo, com `APP_URL=https://seu-dominio`.
3. Faça o deploy. O entrypoint executa, em ordem:
   `migrate --force`, seed de perfis/menus, `napi:bootstrap-admin`, cache de
   produção e, então, Apache + scheduler.
4. Acompanhe os logs. O serviço só inicia se o banco estiver saudável e o
   primeiro administrador puder existir com segurança.
5. No NPM, crie o Proxy Host com scheme `http`, hostname `napi-app` e porta
   `80`. Solicite certificado válido, habilite Force SSL e HTTP/2. HSTS só
   deve ser ligado depois que HTTPS estiver confirmado em todo o domínio.
6. Entre em `/admin/login`, confirme o acesso e troque a senha inicial pela
   interface quando esse fluxo existir. Imediatamente depois, remova
   `NAPI_BOOTSTRAP_ADMIN_PASSWORD` do ambiente e redeploye. O comando detecta
   o administrador ativo e não altera sua senha.

Não mude apenas `DB_PASSWORD` depois que o volume já foi inicializado: o
PostgreSQL aplica `POSTGRES_PASSWORD` na criação do cluster. Uma rotação exige
alterar a senha do role no banco e a variável da aplicação na mesma janela.

## Verificação na VM

Execute antes de apontar o DNS ou liberar o firewall:

```bash
docker compose config --quiet
docker compose build --pull
docker compose up -d
docker compose ps
docker compose logs --tail=200 app db
```

Confirme também, de outra máquina, que apenas 80/443 estão alcançáveis; valide
`/up`, a landing, o login e uma rota autenticada. O ambiente local usado no
desenvolvimento atual não possui Docker, portanto o build e o smoke test da
imagem precisam ser executados na VM.

## Persistência e recuperação

Os volumes `napi_postgres_data` e `napi_storage` são persistentes; caches de
bootstrap são recriados em memória a cada início. O volume de banco precisa de backup automatizado, criptografado
e externo à VM, com teste de restauração. Não trate volume Docker como backup.
Antes de atualizar a imagem, gere backup e valide `composer audit --no-dev`, a
suíte e as migrations em staging.

