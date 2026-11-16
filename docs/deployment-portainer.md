# Deploy com Docker Compose, Portainer e Nginx Proxy Manager

[← Documentação](README.md) · [Revisão de segurança](security/public-deployment-review.md)

O `compose.yaml` tem um único serviço: `app`. O PostgreSQL não faz parte do
stack — ele é provisionado, atualizado e copiado (backup) separadamente, e a
aplicação o acessa por `DB_HOST`/`DB_PORT`. `app` publica `APP_PORT` (8006 por
padrão) no host. O Nginx Proxy Manager (NPM) deve encaminhar para
`http://IP_DA_VM:8006`.

## Pré-requisitos

1. DNS do domínio apontando para a VM.
2. Somente `80/tcp` e `443/tcp` devem ficar públicos. Restrinja `8006` ao IP
   de origem do NPM; restrinja também `81` (NPM admin), `9443` (Portainer) e
   SSH a VPN ou IPs administrativos. Nunca publique `5432`.
3. PostgreSQL externo pronto antes do deploy:
   - banco e role criados, com a role como **dona** do banco (as migrations
     criam tabelas e triggers no schema `public`):
     `CREATE ROLE napi LOGIN PASSWORD '...';` e
     `CREATE DATABASE napi OWNER napi;`;
   - `pg_hba.conf` aceitando a origem do contêiner `app` com `scram-sha-256`;
   - o `app` entra na rede Docker do stack do banco, declarada como externa
     (`DB_NETWORK`, padrão `psql_default`), e usa o nome do contêiner em
     `DB_HOST` (padrão do exemplo: `psql`). Confira o nome real em
     **Networks** no Portainer ou com `docker network ls`; se ela não existir,
     o deploy falha antes de criar o contêiner. A porta `5432` não precisa ser
     publicada. `localhost` e `127.0.0.1` apontam para o próprio contêiner e
     não funcionam;
   - se o banco estiver em outra máquina, `DB_SSLMODE=require` (ou mais estrito).
4. Confirme qual endereço de origem aparece no access log da aplicação quando
   o NPM faz uma requisição. Esse endereço exato entra em `TRUSTED_PROXIES`.
5. Gere valores únicos para `APP_KEY`, senha do banco e senha inicial do
   administrador. A senha que apareceu na conversa deve ser considerada
   comprometida e não pode ser reutilizada.

O modelo de variáveis está em `docker/stack.env.example`. O arquivo preenchido
fica fora do Git; no Portainer, prefira cadastrar os valores no ambiente do
stack. `TRUSTED_PROXIES` recebe somente o IP de origem confirmado do NPM —
nunca `*`.

## Subida pelo Portainer

1. Em **Stacks**, crie um stack a partir deste repositório e selecione
   `compose.yaml`.
2. Cadastre todas as variáveis do modelo, com `APP_URL=https://seu-dominio`.
3. Faça o deploy. O entrypoint executa, em ordem:
   `migrate --force`, seed de perfis/menus, `napi:bootstrap-admin`, cache de
   produção e, então, Apache + scheduler.
4. Acompanhe os logs. Se o banco externo estiver inacessível, o `migrate`
   falha, o contêiner encerra e o Docker o reinicia (`restart: unless-stopped`)
   até a conexão funcionar; corrija `DB_*` em vez de esperar. O serviço também
   não inicia se o primeiro administrador não puder existir com segurança.
5. No NPM, crie o Proxy Host com scheme `http`, hostname igual ao IP da VM e
   porta `8006`. Solicite certificado válido, habilite Force SSL e HTTP/2. HSTS só
   deve ser ligado depois que HTTPS estiver confirmado em todo o domínio.
6. Entre em `/admin/login`, confirme o acesso e troque a senha inicial pela
   interface quando esse fluxo existir. Imediatamente depois, remova
   `NAPI_BOOTSTRAP_ADMIN_PASSWORD` do ambiente e redeploye. O comando detecta
   o administrador ativo e não altera sua senha.

Para um teste temporário sem TLS em `http://IP_DA_VM:8006`, use esse endereço
em `APP_URL` e `SESSION_SECURE_COOKIE=false`. Antes de passar pelo NPM, volte
`APP_URL` para o domínio HTTPS e `SESSION_SECURE_COOKIE=true`.

Para rotacionar a senha do banco, altere a senha da role no PostgreSQL e
`DB_PASSWORD` no stack na mesma janela, depois redeploye.

## Verificação na VM

Execute antes de apontar o DNS ou liberar o firewall:

```bash
docker compose config --quiet
docker compose build --pull
docker compose up -d
docker compose ps
docker compose logs --tail=200 app
```

Confirme também, de uma origem que não seja o NPM, que `8006` está bloqueada e
que apenas 80/443 estão publicamente alcançáveis; valide `/up`, a landing, o
login e uma rota autenticada. O ambiente local usado no
desenvolvimento atual não possui Docker, portanto o build e o smoke test da
imagem precisam ser executados na VM.

## Persistência e recuperação

O stack mantém apenas o volume `napi_storage`; caches de bootstrap são
recriados em memória a cada início. Os dados ficam no PostgreSQL externo, cujo
backup automatizado, criptografado e fora da VM, com teste de restauração, é
responsabilidade de quem opera o banco. Antes de atualizar a imagem, gere
backup e valide `composer audit --no-dev`, a suíte e as migrations em staging —
o entrypoint roda `migrate --force` no banco externo a cada início.

Se uma versão anterior deste stack chegou a subir com o serviço `db`, o
contêiner e o volume `napi_postgres_data` ficam órfãos no host: migre os dados
que existirem (`pg_dump`/`pg_restore`) para o banco externo e só então
remova-os.

