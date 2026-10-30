# Deploy da mídia compartilhada (Hostinger)

O painel e o portal público rodam na mesma conta Hostinger. Os uploads do painel ficam numa pasta servida pelo site de mídia, que o portal público usa para montar as URLs. Não é necessário serviço externo nem cópia manual de arquivos.

**Não altere** o WordPress vivo em `~/domains/cbngo.com.br`.

## URLs

| Papel | URL | Pasta no servidor |
|---|---|---|
| Painel | https://painel.cbngo.org.br | `/home/u579305537/domains/painel.cbngo.org.br` |
| Mídia | https://media.cbngo.org.br | `/home/u579305537/domains/media.cbngo.org.br/public_html` |
| Portal público | https://cbngo.org.br | `/home/u579305537/domains/cbngo.org.br` |

No `.env` de produção do painel:

```dotenv
MEDIA_ROOT=/home/u579305537/domains/media.cbngo.org.br/public_html
MEDIA_URL=https://media.cbngo.org.br
```

No `.env` do portal público (`~/domains/cbngo.org.br/.env`):

```dotenv
MEDIA_URL=https://media.cbngo.org.br
```

O `public_html` da mídia tem um `.htaccess` que desliga listagem de diretório, recusa execução de PHP e cacheia imagens. O usuário PHP do painel já tem escrita nessa pasta (mesma conta).

O domínio da convenção é `cbngo.org.br`; `cbngo.com.br` continua sendo o WordPress antigo. Os dois hosts são tratados como internos em `config/portal.php`.

## Ao trocar o domínio da mídia

Conectar um domínio a um site no hPanel **renomeia a pasta do site**. Foi o que aconteceu em 30/10/2026: `peachpuff-ant-612575.hostingersite.com` virou `media.cbngo.org.br`, e o endereço temporário passou a mostrar uma página de domínio estacionado. Enquanto o painel ainda apontava para a pasta antiga, um upload recriou essa pasta (que nenhum domínio serve) e o logotipo não apareceu no portal.

Sempre que o domínio ou a pasta da mídia mudar:

1. Ative o SSL do domínio no hPanel.
2. Copie para a pasta nova os arquivos que tenham sido gravados na pasta antiga depois da troca.
3. Atualize `MEDIA_ROOT` e `MEDIA_URL` no `.env` do painel e recrie os caches:

   ```bash
   cd ~/domains/painel.cbngo.org.br
   /opt/alt/php83/usr/bin/php artisan optimize:clear
   bash scripts/post-deploy.sh
   ```

4. Atualize o `MEDIA_URL` do portal público e recrie os caches dele:

   ```bash
   cd ~/domains/cbngo.org.br
   /opt/alt/php83/usr/bin/php artisan optimize:clear
   /opt/alt/php83/usr/bin/php artisan optimize
   ```

5. Confira uma imagem: `curl -I https://media.cbngo.org.br/<caminho>` deve responder `200` com `Content-Type: image/...`.

## PHP

O projeto exige PHP 8.2 ou superior. O site do painel está em 8.3 no hPanel. O `php` padrão do SSH é 8.1; use `/opt/alt/php83/usr/bin/php` nos comandos `artisan` e no cron. Detalhes em [deploy-producao.md](deploy-producao.md).

## Primeira publicação / migração de arquivos

```bash
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan media:migrate-public --dry-run
/opt/alt/php83/usr/bin/php artisan media:migrate-public
/opt/alt/php83/usr/bin/php artisan media:variants
```

- `media:migrate-public` copia para `MEDIA_ROOT` os arquivos que estavam no disco `public` do painel (`storage/app/public`). Ele é idempotente: arquivos que já existem no destino são ignorados.
- `media:variants` gera as versões WebP (`thumb`, `card`, `cover`) que ainda não existem. Use `--force` para regerar todas.

## Funcionamento no dia a dia

- As versões WebP são geradas logo após a resposta da requisição que salvou o registro (`dispatchAfterResponse`), sem worker de fila.
- Se alguma versão faltar (por exemplo, por timeout num upload muito grande), rode `php artisan media:variants` de novo.
- O portal público monta as URLs com `MEDIA_URL` (veja `docs/integracao-portal-publico.md`).

## Trocar para um storage externo no futuro

O código só conhece o nome do disco (`config/media.php`). Para usar Cloudflare R2 ou S3, troque a definição do disco `media` em `config/filesystems.php` por uma do driver `s3`, instale `league/flysystem-aws-s3-v3` e aponte `MEDIA_URL` para o domínio público do bucket.
