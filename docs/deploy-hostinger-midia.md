# Deploy da mídia compartilhada (Hostinger)

O painel e o portal público rodam na mesma conta Hostinger. Os uploads do painel ficam numa pasta servida pelo site de mídia, que o portal público usa para montar as URLs. Não é necessário serviço externo nem cópia manual de arquivos.

**Não altere** o WordPress vivo em `~/domains/cbngo.com.br`.

## URLs atuais (temporárias)

A primeira publicação usa os sites Hostinger já criados, sem DNS customizado:

| Papel | URL | Pasta no servidor |
|---|---|---|
| Painel | https://indigo-owl-926716.hostingersite.com | `/home/u579305537/domains/indigo-owl-926716.hostingersite.com` |
| Mídia | https://peachpuff-ant-612575.hostingersite.com | `/home/u579305537/domains/peachpuff-ant-612575.hostingersite.com/public_html` |
| Portal público | https://honeydew-wolverine-903854.hostingersite.com | reservado |

No `.env` de produção do painel:

```dotenv
MEDIA_ROOT=/home/u579305537/domains/peachpuff-ant-612575.hostingersite.com/public_html
MEDIA_URL=https://peachpuff-ant-612575.hostingersite.com
```

O `public_html` da mídia tem um `.htaccess` que desliga listagem de diretório, recusa execução de PHP e cacheia imagens. O usuário PHP do painel já tem escrita nessa pasta (mesma conta).

## Quando o subdomínio customizado existir

1. No hPanel, crie `midia.cbngo.com.br` (ou aponte o domínio para a pasta peachpuff).
2. Ative SSL Let's Encrypt.
3. Atualize o `.env` do painel:

```dotenv
MEDIA_ROOT=/home/u579305537/domains/midia.cbngo.com.br/public_html
MEDIA_URL=https://midia.cbngo.com.br
```

Se a pasta do subdomínio for outra, mova (ou sirva) o conteúdo atual de `peachpuff-ant-612575.hostingersite.com/public_html` para lá antes de trocar `MEDIA_ROOT`.

A documentação antiga citava `midia.cbngo.org.br`; o domínio oficial em produção hoje é `cbngo.com.br`. Os dois hosts continuam tratados como internos em `config/portal.php`.

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
