# Integração Painel ↔ Portal Público

O painel administrativo (`cbn-go/portal-cbngo-admin`) e o portal público (`cbn-go/portal-cbngo-site`) usam **um único banco MySQL**.

- **O painel é o dono do schema.** Só ele executa `php artisan migrate`. O portal público não mantém migrations de conteúdo em produção.
- **O portal público só lê.** Em produção, ele conecta com um usuário que tem apenas `SELECT`:

  ```sql
  CREATE USER 'cbngo_site'@'%' IDENTIFIED BY '...';
  GRANT SELECT ON cbngo.* TO 'cbngo_site'@'%';
  ```

- **Arquivos** (`featured_image`, `gallery`, `cover_image`, `avatar`, `logo`, `board_members.photo`, `documents.file`) são caminhos relativos no disco de mídia compartilhado (`media`), por exemplo `news/featured/abc.jpg`. O portal monta a URL final como `MEDIA_URL/{caminho}`, com `MEDIA_URL=https://midia.cbngo.org.br` em produção. Veja [Mídia compartilhada](#mídia-compartilhada).
- **Conteúdo** (`content`) é HTML gerado pelo RichEditor. Em `articles` e `news` o HTML é sanitizado pelo painel ao salvar (`HtmlSanitizerService`). Em `notices` ele **não** é sanitizado no servidor, então o portal deve tratá-lo como HTML não confiável.

## Regra de publicação (`news` e `articles`)

Um registro é público quando:

```
status = 'published' AND published_at IS NOT NULL AND published_at <= NOW()
```

Os demais status nunca devem ser exibidos: `draft`, `pending_review` e `archived`. O painel preenche `published_at` automaticamente ao publicar, caso a data esteja vazia.

## `news` (site: `news`)

| Painel | Site (antes) | Observação |
|---|---|---|
| `title`, `slug`, `excerpt`, `content` | iguais | `slug` é único |
| `category` | `category` | Lista fixa (`App\Enums\NewsCategory`); o valor salvo é o rótulo exibido: Geral, Comunicado Oficial, Missões, Educação Teológica, Eventos, Ação Social |
| `featured_image` | `image_url` | Caminho no disco de mídia |
| `gallery` (JSON, lista de caminhos) | `gallery_images` | Até 10 imagens, na ordem definida pelo editor |
| `church_id` → `churches.name` / `churches.city` | `church_name` / `city` | `church_id` nulo significa notícia da CBN-GO (Estadual) |
| `is_official` | `is_official` | |
| `event_date` | — | Data do evento, opcional |
| `status` + `published_at` | `is_published` + `published_at` | Ver a regra de publicação |

## `articles` (site: `articles`)

| Painel | Site (antes) | Observação |
|---|---|---|
| `title`, `slug`, `excerpt`, `content` | iguais | |
| `category` | `category` | Lista fixa (`App\Enums\ArticleCategory`): Teologia, Missões, Liderança, Família, Discipulado, Devocional |
| `reading_time` | `reading_time` | Minutos, calculado pelo painel ao salvar (200 palavras/min, mínimo 1) |
| `cover_image` | — | Caminho no disco de mídia |
| `user_id` → `author_profiles.user_id` | `author_id` | O autor é o usuário; o perfil público fica em `author_profiles` |
| `status` + `published_at` | `is_published` + `published_at` | Ver a regra de publicação |

## Autores: `author_profiles` + `users` (site: `authors`)

| Painel | Site (antes) | Observação |
|---|---|---|
| `author_profiles.slug` | `slug` | Rota `/autores/{slug}`. Gerado uma vez a partir do nome e não muda depois |
| `users.name` | `name` | Nome de exibição (`AuthorProfile::$display_name`) |
| `author_profiles.pastoral_title` | `role_title` | O site exibe "Articulista CBN-GO" quando vazio |
| `author_profiles.avatar` | `avatar_url` | Caminho no disco de mídia |
| `author_profiles.bio` | `bio` | |
| `author_profiles.social_links` (JSON) | `social_links` | Chaves: `instagram`, `facebook`, `youtube`, `linkedin`, `website` |

O perfil é criado quando o autor salva **Meu Perfil de Autor** no portal (`/portal/profile`). Considere apenas usuários ativos (`users.is_active = 1`).

## `churches` (site: `churches`)

| Painel | Site (antes) | Observação |
|---|---|---|
| `name`, `slug`, `pastor_name` | iguais | `slug` é único |
| `logo` | `logo_url` | Caminho no disco de mídia |
| `address`, `number`, `complement` | `address` | Montar como `address, number – complement` |
| `neighborhood`, `city`, `state`, `zip_code` | iguais | |
| `latitude`, `longitude` | iguais | `decimal(10,7)`, opcionais |
| `phone`, `cellphone`, `email` | `phone`, `email` | |
| `social_links` (JSON objeto) | `social_links` | Chave = canal, valor = URL |
| `service_times` (JSON lista) | `service_times` | `[{"day": "Domingo", "time": "19h00", "label": "Culto de Celebração"}]` |
| `is_active` | `is_active` | Exibir apenas igrejas ativas |

## `notices` (site: `urgent_notices`)

| Painel | Site (antes) | Observação |
|---|---|---|
| `title` | `title` | |
| `content` | `message` | HTML **não** sanitizado no servidor |
| `action_url` | `link` | Opcional; se informado, `action_label` também é obrigatório |
| `action_label` | `link_text` | Usar "Saiba mais" quando vazio |
| `priority` | `type` | `normal → info`, `high → warning`, `urgent → critical` (`NoticePriority::publicBannerType()`) |
| `starts_at` | `starts_at` | Nulo = vigente desde sempre |
| `expires_at` | `ends_at` | Nulo = sem prazo de expiração |
| `is_active` | `is_active` | |

Um aviso está vigente quando `is_active = 1`, `starts_at` é nulo ou `<= NOW()`, e `expires_at` é nulo ou `>= NOW()`. É a mesma regra de `Notice::currentlyActive()`. Quando houver mais de um aviso vigente, exiba o de maior prioridade (`urgent` > `high` > `normal`) e, em caso de empate, o mais recente.

## `board_members` (site: `board_members`)

Gerido em **Institucional → Diretoria**, apenas pelo super administrador.

| Painel | Site (antes) | Observação |
|---|---|---|
| `name`, `role` | iguais | |
| `group` | `group` | Mesmas chaves de `BoardMember::GROUPS` do site (`App\Enums\BoardGroup`), na ordem de exibição: `presidencia`, `vice_presidencia`, `secretaria`, `tesouraria`, `conselho_fiscal`, `coordenacao_regional` |
| `region` | `region` | Preenchido apenas para `coordenacao_regional` |
| `term_label` | `term_label` | Ex.: "Gestão 2025–2027" |
| `photo` | `photo_url` | Caminho no disco de mídia (`board/photos/...`); nulo = exibir as iniciais |
| `position` | `position` | Ordem única da diretoria, definida arrastando as linhas no painel |
| `is_active` | `is_active` | |

Exibir: `is_active = 1`, agrupado por `group` na ordem acima e ordenado por `position`.

## `documents` (site: `documents`)

Gerido em **Institucional → Documentos**, apenas pelo super administrador.

| Painel | Site (antes) | Observação |
|---|---|---|
| `title`, `slug`, `description` | iguais | `slug` é único |
| `category` | `category` | `estatuto`, `regimento`, `pacto`, `atas`, `outros` (`App\Enums\DocumentCategory`) |
| `file` | `file_url` | Caminho no disco de mídia (`documents/files/...`), PDF, DOC ou DOCX |
| `external_url` | `file_url` | Link externo usado quando não há arquivo. Exatamente um dos dois é preenchido |
| `file_format` | `file_format` | Detectado pelo painel ao salvar: `PDF`, `DOC`, `DOCX` ou `Página` (link para uma página web) |
| `position` | `position` | Ordem definida arrastando as linhas no painel |
| `is_active` | `is_active` | |

URL do documento: `MEDIA_URL/{file}` quando `file` está preenchido; senão, `external_url`. Exibir: `is_active = 1 ORDER BY position`.

## Mídia compartilhada

Todos os uploads do painel vão para o disco `media`, servido em produção pelo subdomínio `midia.cbngo.org.br` (veja `docs/deploy-hostinger-midia.md`).

- **URL final:** `MEDIA_URL` + `/` + caminho salvo no banco. Nunca há URL absoluta no banco para esses campos.
- **Versões WebP:** toda imagem (`featured_image`, itens de `gallery`, `cover_image`, `avatar`, `logo`, `photo`) ganha três versões ao lado do original, geradas logo após o salvamento:

  | Versão | Largura máxima | Exemplo para `news/featured/abc.jpg` |
  |---|---|---|
  | `thumb` | 400 px | `news/featured/abc-thumb.webp` |
  | `card` | 800 px | `news/featured/abc-card.webp` |
  | `cover` | 1600 px | `news/featured/abc-cover.webp` |

  A proporção é mantida e a imagem nunca é ampliada (uma imagem de 600 px gera `card` e `cover` com 600 px). Use as versões em `srcset` e mantenha o original como fallback, porque as versões podem levar alguns segundos para existir após o upload.
- **Documentos** (`documents.file`) não têm versões.
- **Anexos do RichEditor** (`news/attachments`, `articles/attachments`) ficam embutidos no HTML de `content` já com a URL absoluta de `MEDIA_URL`.
- Quando uma imagem é substituída ou o registro é excluído, o painel apaga o original e as versões.

## Dados de desenvolvimento

`php artisan migrate:fresh --seed` em ambiente `local` cria:

- as 8 igrejas polo com slug, coordenadas e horários de culto;
- 3 autores com perfil;
- artigos e notícias em todas as categorias, incluindo rascunhos e conteúdo pendente de revisão;
- avisos vigente, agendado e expirado;
- a diretoria da gestão 2025–2027 e os documentos Estatuto, Regimento Interno e Pacto Renovado (como links).

Veja `Database\Seeders\DevelopmentContentSeeder`.
