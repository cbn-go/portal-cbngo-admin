# Deploy da mídia compartilhada (Hostinger)

O painel e o portal público rodam na mesma conta Hostinger, no domínio `cbngo.org.br`. Os uploads do painel ficam numa pasta servida pelo subdomínio `midia.cbngo.org.br`, que o portal público usa para montar as URLs. Não é necessário serviço externo nem cópia manual de arquivos.

## 1. Domínio e subdomínio

1. No hPanel, adicione `cbngo.org.br` à conta (depois da compra do domínio).
2. Crie o subdomínio `midia.cbngo.org.br`. A Hostinger cria a pasta `~/domains/midia.cbngo.org.br/public_html`.
3. Ative SSL para o subdomínio.

## 2. Configuração do painel

No `.env` de produção do painel:

```dotenv
MEDIA_ROOT=/home/<usuario>/domains/midia.cbngo.org.br/public_html
MEDIA_URL=https://midia.cbngo.org.br
```

O usuário que executa o PHP do painel precisa ter permissão de escrita em `MEDIA_ROOT`. Como tudo roda na mesma conta, isso já vale por padrão.

## 3. PHP

O projeto exige PHP 8.2 ou superior. Na conta, o `php` padrão da linha de comando é 8.1, mas há versões mais novas instaladas em `/opt/alt` (8.4+). Selecione a versão do domínio no hPanel e use o binário correspondente nos comandos `artisan` e no cron.

## 4. Primeira publicação

```bash
php artisan migrate --force
php artisan media:migrate-public --dry-run
php artisan media:migrate-public
php artisan media:variants
```

- `media:migrate-public` copia para `MEDIA_ROOT` os arquivos que estavam no disco `public` do painel (`storage/app/public`). Ele é idempotente: arquivos que já existem no destino são ignorados.
- `media:variants` gera as versões WebP (`thumb`, `card`, `cover`) que ainda não existem. Use `--force` para regerar todas.

## 5. Funcionamento no dia a dia

- As versões WebP são geradas logo após a resposta da requisição que salvou o registro (`dispatchAfterResponse`), sem worker de fila.
- Se alguma versão faltar (por exemplo, por timeout num upload muito grande), rode `php artisan media:variants` de novo.
- O portal público monta as URLs com `MEDIA_URL` (veja `docs/integracao-portal-publico.md`).

## Trocar para um storage externo no futuro

O código só conhece o nome do disco (`config/media.php`). Para usar Cloudflare R2 ou S3, troque a definição do disco `media` em `config/filesystems.php` por uma do driver `s3`, instale `league/flysystem-aws-s3-v3` e aponte `MEDIA_URL` para o domínio público do bucket.
