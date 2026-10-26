# Deploy de produção do painel (Hostinger)

Conta compartilhada Hostinger (LiteSpeed + CloudLinux). Equivale ao critério 10.1 da [issue #10](https://github.com/cbn-go/portal-cbngo-admin/issues/10): PHP 8.2+, HTTPS e HTTP/2. Não há Nginx nem PHP-FPM gerenciado por nós.

**Não publique neste caminho:** `~/domains/cbngo.com.br` (WordPress vivo).

## Sites

| Papel | URL atual | Pasta |
|---|---|---|
| Painel (este repo) | https://indigo-owl-926716.hostingersite.com | `~/domains/indigo-owl-926716.hostingersite.com` |
| Mídia (uploads) | https://peachpuff-ant-612575.hostingersite.com | `~/domains/peachpuff-ant-612575.hostingersite.com/public_html` |
| Portal público | https://honeydew-wolverine-903854.hostingersite.com | reservado; fora desta issue |

SSH: host `cbn-go` em `~/.ssh/config` (`82.180.153.107:65002`, user `u579305537`).

O document root do painel é `public_html/`. O Laravel usa essa pasta como `public/` quando ela existe (`AppServiceProvider`).

## PHP

O site indigo-owl está em **PHP 8.3** (hPanel). Artisan e cron usam o mesmo major:

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
0 3 * * * /bin/bash /home/u579305537/domains/indigo-owl-926716.hostingersite.com/scripts/backup-producao.sh
```

Não há binário `crontab` no SSH desta conta.

## Primeiro usuário

Não rode `DatabaseSeeder` nem `SuperAdminSeeder` em produção (senha `password`). Depois do migrate:

```bash
/opt/alt/php83/usr/bin/php artisan tinker --execute="App\Models\User::query()->updateOrCreate(['email' => 'superadmin@cbngo.org.br'], ['name' => 'Super Admin', 'password' => 'SENHA_FORTE', 'role' => App\Enums\UserRole::SUPER_ADMIN, 'church_id' => null, 'is_active' => true, 'email_verified_at' => now()]);"
```

Painel: `https://indigo-owl-926716.hostingersite.com/admin/login`

## Subdomínio customizado (depois)

1. No hPanel, crie `painel.cbngo.com.br` apontando para a pasta do indigo-owl (ou recrie o addon e migre arquivos).
2. Crie `midia.cbngo.com.br` apontando para a pasta peachpuff.
3. Ative SSL Let's Encrypt nos dois.
4. Atualize `APP_URL`, `MEDIA_ROOT` e `MEDIA_URL` no `.env` e rode `scripts/post-deploy.sh`.

## Checagens

```bash
curl -I https://indigo-owl-926716.hostingersite.com/up
curl -o /dev/null -s -w '%{time_total}\n' https://indigo-owl-926716.hostingersite.com/up
curl -o /dev/null -s -w '%{time_total}\n' https://indigo-owl-926716.hostingersite.com/admin/login
```
