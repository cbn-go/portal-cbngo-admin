# Deploy de produção do painel (Hostinger)

Conta compartilhada Hostinger (LiteSpeed + CloudLinux). Equivale ao critério 10.1 da [issue #10](https://github.com/cbn-go/portal-cbngo-admin/issues/10): PHP 8.2+, HTTPS e HTTP/2. Não há Nginx nem PHP-FPM gerenciado por nós.

**Não publique neste caminho:** `~/domains/cbngo.com.br` (WordPress vivo).

## Sites

| Papel | URL atual | Pasta |
|---|---|---|
| Painel (este repo) | https://painel.cbngo.org.br | `~/domains/painel.cbngo.org.br` |
| Mídia (uploads) | https://media.cbngo.org.br | `~/domains/media.cbngo.org.br/public_html` |
| Portal público | https://cbngo.org.br | `~/domains/cbngo.org.br` (repo `portal-cbngo-site`) |

SSH: host `cbn-go` em `~/.ssh/config` (`82.180.153.107:65002`, user `u579305537`).

O document root do painel é `public_html/`. O Laravel usa essa pasta como `public/` quando ela existe (`AppServiceProvider`).

## PHP

O site do painel está em **PHP 8.3** (hPanel). Artisan e cron usam o mesmo major:

```bash
/opt/alt/php83/usr/bin/php artisan …
```

O `php` padrão do SSH continua 8.1 — não use para o painel.

## Banco

MySQL na mesma conta, dono do schema = painel:

- database: `u579305537_cbngo`
- user: `u579305537_cbngo`
- host no servidor: `localhost`

O `.env` de produção fica só no servidor e **não** entra no rsync.

## Pipeline

Push (ou `workflow_dispatch`) em `main` roda CI (`quality` + `tests-mysql`) e, se passar, o job `deploy` em [`.github/workflows/ci.yml`](../.github/workflows/ci.yml):

1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build`
3. rsync do app (sem `.env` e sem `storage/`)
4. rsync de `public/` → `public_html/`
5. [`scripts/post-deploy.sh`](../scripts/post-deploy.sh): `migrate`, `storage:link`, `config:cache`, `route:cache`, `view:cache`, `icons:cache`

Secret necessário: `HOSTINGER_SSH_KEY` (chave privada ed25519 de deploy, cuja pública está em `~/.ssh/authorized_keys` no servidor).

## Backup

[`scripts/backup-producao.sh`](../scripts/backup-producao.sh) grava dump MySQL e tar da mídia em `~/backups/portal-admin/`, retenção 7 dias.

Cron no hPanel (03:00 America/Sao_Paulo; o servidor pode estar em UTC — ajuste se o horário real sair diferente):

```cron
0 3 * * * /bin/bash /home/u579305537/domains/painel.cbngo.org.br/scripts/backup-producao.sh
```

Não há binário `crontab` no SSH desta conta.

## Primeiro usuário

Não rode `DatabaseSeeder` nem `SuperAdminSeeder` em produção (senha `password`). Depois do migrate:

```bash
/opt/alt/php83/usr/bin/php artisan tinker --execute="App\Models\User::query()->updateOrCreate(['email' => 'superadmin@cbngo.org.br'], ['name' => 'Super Admin', 'password' => 'SENHA_FORTE', 'role' => App\Enums\UserRole::SUPER_ADMIN, 'church_id' => null, 'is_active' => true, 'email_verified_at' => now()]);"
```

Painel: `https://painel.cbngo.org.br/admin/login`

## Domínios definitivos

O domínio da convenção é `cbngo.org.br`. Ao conectar um domínio a um site da Hostinger, **o hPanel renomeia a pasta do site** (`~/domains/<site-temporario>` vira `~/domains/<domínio>`). Foi o que aconteceu com o painel (`indigo-owl-926716.hostingersite.com` → `painel.cbngo.org.br`) e com o portal (`honeydew-wolverine-903854.hostingersite.com` → `cbngo.org.br`).

Depois de conectar (ou trocar) o domínio de um site:

1. Atualize `DEPLOY_PATH` no job `deploy` de `.github/workflows/ci.yml` (o rsync usa `--delete` e publicaria na pasta antiga).
2. No servidor, ajuste o `.env` (`APP_URL` e, se for a mídia, `MEDIA_ROOT`/`MEDIA_URL`) e **recrie os caches**, porque o `config:cache` guarda os caminhos absolutos da pasta antiga:

   ```bash
   cd ~/domains/painel.cbngo.org.br
   /opt/alt/php83/usr/bin/php artisan optimize:clear
   bash scripts/post-deploy.sh
   ```

3. Atualize o caminho do cron de backup no hPanel.
4. Confira o SSL do domínio no hPanel.

A mídia está em `media.cbngo.org.br`; para trocar o domínio dela, siga [deploy-hostinger-midia.md](deploy-hostinger-midia.md#ao-trocar-o-domínio-da-mídia) (o portal público também usa o `MEDIA_URL`).

## Checagens

```bash
curl -I https://painel.cbngo.org.br/up
curl -o /dev/null -s -w '%{time_total}\n' https://painel.cbngo.org.br/up
curl -o /dev/null -s -w '%{time_total}\n' https://painel.cbngo.org.br/admin/login
```
