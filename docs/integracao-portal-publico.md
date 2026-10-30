# Integração Painel ↔ Portal Público

O painel administrativo (`cbn-go/portal-cbngo-admin`) e o portal público (`cbn-go/portal-cbngo-site`) usam **um único banco MySQL**.

- **O painel é o dono do schema.** Só ele executa `php artisan migrate`. O portal público não mantém migrations de conteúdo em produção.
- **O portal público só lê.** Em produção, ele conecta com um usuário que tem apenas `SELECT`:

  ```sql
  CREATE USER 'cbngo_site'@'%' IDENTIFIED BY '...';
  GRANT SELECT ON cbngo.* TO 'cbngo_site'@'%';
  ```

- **Arquivos** (`featured_image`, `gallery`, `cover_image`, `avatar`, `logo`, `board_members.photo`, `documents.file`) são caminhos relativos no disco de mídia compartilhado (`media`), por exemplo `news/featured/abc.jpg`. O portal monta a URL final como `MEDIA_URL/{caminho}`, com `MEDIA_URL=https://media.cbngo.org.br` em produção. Veja [Mídia compartilhada](#mídia-compartilhada).
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
| `neighborhood`, `city`, `state`, `zip_code` | iguais | `city` é mantido canônico e sincronizado com o município oficial |
| `municipality_id` → `municipalities.id` | — | Vínculo relacional com a base territorial do IBGE (`municipalities`). Nulo apenas se cidade externa |
| `latitude`, `longitude` | iguais | `decimal(10,7)`, opcionais |
| `phone`, `cellphone`, `email` | `phone`, `email` | |
| `social_links` (JSON objeto) | `social_links` | Chave = canal, valor = URL |
| `service_times` (JSON lista) | `service_times` | `[{"day": "Domingo", "time": "19h00", "label": "Culto de Celebração"}]` |
| `is_active` | `is_active` | Exibir apenas igrejas ativas |

## `municipalities` (Base Territorial de Goiás - IBGE)

Base canônica com os 246 municípios do estado de Goiás e suas respectivas regiões imediatas, intermediárias, microrregiões e mesorregiões do IBGE (utilizada no Painel de Estratégia e no vínculo das igrejas).

| Coluna | Tipo | Observação |
|---|---|---|
| `ibge_code` | `string(7)` | Código do município no IBGE de 7 dígitos único (ex.: `5208707` para Goiânia) |
| `name` | `string(100)` | Nome oficial do município |
| `slug` | `string(120)` | Slug único (ex.: `goiania`) |
| `normalized_name` | `string(100)` | Nome normalizado sem acento/pontuação em minúsculas para buscas rápidas |
| `immediate_region_code` / `immediate_region_name` | `string` | Região Geográfica Imediata (ex.: `520001` - Goiânia) |
| `intermediate_region_code` / `intermediate_region_name` | `string` | Região Geográfica Intermediária (ex.: `5201` - Goiânia) |
| `micro_region_name` / `meso_region_name` | `string` | Divisão tradicional do IBGE |


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
| `title` | — (novo) | Título eclesiástico já abreviado (`Pr.`, `Pra.`, `Ev.`, `Miss.`, `Diác.`, `Presb.`); nulo = leigo. Exibir como `{title} {name}` (`App\Enums\ClergyTitle`) |
| `name`, `role` | iguais | `name` não traz mais o título |
| `group` | `group` | Mesmas chaves de `BoardMember::GROUPS` do site (`App\Enums\BoardGroup`), na ordem de exibição: `presidencia`, `vice_presidencia`, `secretaria`, `tesouraria`, `conplex` (novo: "Conselho de Planejamento e Execução (CONPLEX)"), `conselho_fiscal`, `coordenacao_regional` (rótulo "Diretorias Regionais (AMIGO)") |
| `region` | `region` | Só em `coordenacao_regional`. Chaves fixas, na ordem de exibição: `leste`, `oeste`, `norte`, `sudoeste` (rótulo "AMIGO Leste" etc., `App\Enums\AmigoRegion`) |
| `term_label` | `term_label` | Gerado a partir das datas: "Triênio 2026–2029" |
| `term_starts_on`, `term_ends_on` | — (novos) | Datas do mandato (`2026-10-01` a `2029-09-30`) |
| `photo` | `photo_url` | Caminho no disco de mídia (`board/photos/...`); nulo = exibir as iniciais |
| `position` | `position` | Ordem única da diretoria, definida arrastando as linhas no painel |
| `is_active` | `is_active` | |

Exibir: `is_active = 1`, agrupado por `group` na ordem acima e ordenado por `position`. Em `coordenacao_regional`, agrupar também por `region` (Leste, Oeste, Norte, Sudoeste), cada região com seus quatro cargos.

A composição do triênio 2026–2029 é carregada por `Database\Seeders\BoardTerm20262029Seeder`, que desativa os membros de gestões anteriores.

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

## `banners` (site: avisos da home / hero 1:1)

Gerido em **Comunicação → Avisos da Home** pelo super administrador e pelo editor da convenção. Não confundir com `notices`, que continuam sendo a barra fina no topo do site.

**O aviso da home adota foto quadrada 1:1** no estilo post de rede social, com o texto estruturado ao lado (conforme especificado na issue #46 e no mockup `mockup-home-avisos-1x1.html`).

| Coluna | Tipo | Observação |
|---|---|---|
| `title` | `string(70)` | **Título principal** exibido no site em destaque ao lado da foto (obrigatório) |
| `tag` | `string(30)` | **Selo de categoria** exibido no topo da foto e acima do título (ex.: "Assembleia", "Juventude", "Ação social", "SETEBAN-GO") |
| `subtitle` | `string(160)` | **Subtítulo explicativo** exibido abaixo do título |
| `meta` | `string(60)` | **Linha de apoio** com data, período e local exibida ao lado do botão (ex.: "14–15 mar · Anápolis") |
| `image` | `string(255)` | Foto quadrada 1:1 sem texto, recomendado 1080×1080 (mínimo 800×800). Caminho no disco de mídia (`banners/...`); URL `MEDIA_URL/{image}`, com as versões WebP `-thumb` (160px), `-card` (800px) e `-cover` (1080px) |
| `alt_text` | `string(500)` | Descrição da foto para leitores de tela e acessibilidade |
| `link_label` | `string(60)` | **Texto do botão** de ação (padrão "Saiba mais") |
| `link_url` | `string(255)` | Caminho do site (`/eventos/assembleia`) ou URL completa `http(s)://` |
| `opens_in_new_tab` | `boolean` | Quando `1`, abrir com `target="_blank" rel="noopener noreferrer"` |
| `position` | `integer` | Ordem do carrossel, definida arrastando as linhas no painel |
| `starts_at`, `ends_at` | `datetime` | Opcionais. Nulos = sem início/fim |
| `is_square` | `boolean` | `1` = foto quadrada 1:1 compatível com a nova hero; `0` = legado 16:9 desativado |
| `is_active` | `boolean` | `1` = ativo |

### Regra de exibição no site público

Um aviso é exibido no carrossel da home quando:

```sql
is_active = 1
AND is_square = 1
AND (starts_at IS NULL OR starts_at <= NOW())
AND (ends_at IS NULL OR ends_at >= NOW())
ORDER BY position
```

É a mesma regra de `Banner::visible()`. Sem avisos visíveis, o site exibe a hero institucional padrão.

## `social_projects` (site: `/projetos-sociais`)

Gerido em **Conteúdo → Projetos Sociais**. O representante da igreja cadastra os projetos da própria igreja e envia para revisão; o super administrador e o editor da convenção aprovam a publicação. Quando o representante edita um projeto publicado, ele volta para revisão e sai do site até ser aprovado de novo.

| Coluna | Observação |
|---|---|
| `title`, `slug` | `slug` é único; rota `/projetos-sociais/{slug}` |
| `category` | Valor = rótulo exibido (`App\Enums\ProjectCategory`): `Social`, `Cultural`, `Educação`, `Liderança` |
| `summary` | Até 300 caracteres, para os cards |
| `description` | HTML sanitizado pelo painel ao salvar (`HtmlSanitizerService`) |
| `church_id` → `churches` | Igreja responsável (obrigatória); link para `/igrejas` |
| `city` | Cidade do projeto (padrão: cidade da igreja) |
| `cover_image`, `gallery` (JSON, lista de caminhos) | Disco de mídia (`projects/covers`, `projects/galleries`), com versões WebP |
| `target_audience`, `beneficiaries_count` | Opcionais |
| `started_at`, `ended_at` | Datas. `ended_at` nulo = em andamento |
| `contact_name`, `contact_phone`, `contact_email`, `how_to_help` | Opcionais. `contact_phone` no formato `(62) 99999-9999`; usar para o botão de WhatsApp |
| `status` + `published_at` | Ver a regra abaixo |
| `is_featured` | Destaque definido pela administração |
| `review_notes` | Uso interno do painel (motivo da devolução). Não exibir |

Um projeto é público quando:

```
status = 'published' AND published_at IS NOT NULL AND published_at <= NOW()
```

É a regra de `SocialProject::published()`. Ordenar por `is_featured DESC, published_at DESC`. Um projeto está **em andamento** quando `ended_at IS NULL OR ended_at >= CURDATE()`.

Categorias e o Pacto de Lausanne (`ProjectCategory::description()` e `lausanneReference()`):

| Categoria | Referência |
|---|---|
| Social | Art. 5: Responsabilidade social cristã |
| Cultural | Art. 10: Evangelização e cultura |
| Educação | Art. 11: Educação e liderança |
| Liderança | Art. 11: Educação e liderança |

## `statutes` (site: `/estatuto`)

O estatuto vigente e seu histórico de versões são armazenados na tabela `statutes`. A fonte da verdade do texto da lei é o campo `akn_xml`, estruturado no padrão **Akoma Ntoso 3.0 (OASIS LegalDocML)**.

| Painel | Site | Observação |
|---|---|---|
| `title` | título exibido | Ex: "Estatuto da Convenção Batista Nacional do Estado de Goiás" |
| `version_label` | versão | Ex: `2025-10-18` (único) |
| `approved_at` | data de aprovação | Data da Assembleia Geral (`2025-10-18`) |
| `approved_place` | local de aprovação | Ex: "Anápolis, GO" |
| `approval_body` | órgão deliberativo | Ex: "Assembleia Geral" |
| `minutes_reference` | referência da ata | Ex: "Ata da Assembleia Geral Extraordinária de 18 de outubro de 2025" |
| `registry_info` | dados cartorários | Cartório de Pessoas Jurídicas, averbação à margem do registro nº 806 |
| `effective_at` | início da vigência | Data (`2025-10-18`) |
| `status` | status da versão | `published`, `archived`, `draft`, `pending_review` (`App\Enums\PublishStatus`) |
| `akn_xml` | XML do estatuto | XML Akoma Ntoso 3.0 canônico validado contra o XSD oficial |
| `original_pdf` | PDF digitalizado | Caminho no disco `media` com o documento físico registrado |
| `published_at` | data de publicação | Preenchido automaticamente ao publicar |

### Regra de exibição da versão vigente
O portal público consulta a versão vigente com:
```sql
SELECT * FROM statutes WHERE status = 'published' LIMIT 1;
```
Apenas uma versão pode ter `status = 'published'` simultaneamente. Quando uma nova versão é publicada, a anterior é automaticamente arquivada (`status = 'archived'`).

### Estrutura e Âncoras do Akoma Ntoso (`eId`)
O portal público pode renderizar a página `/estatuto` navegável por capítulos, seções e artigos, gerando âncoras diretas usando os atributos `eId`:
- Capítulos: `chp_1` a `chp_6` (ex: `/estatuto#chp_3`)
- Seções: `chp_3__sec_1` a `chp_3__sec_5`
- Artigos: `art_1` a `art_21` (ex: `/estatuto#art_8`)
- Parágrafos: `art_8__para_1`, `art_5__para_uniq`, `art_12__para_4_a`
- Incisos e Alíneas: `art_8__point_I`, `art_8__point_I__point_a`

### Privacidade do PDF Original
O arquivo apontado por `original_pdf` contém carimbos com CPFs, números de OAB e assinaturas físicas dos membros da diretoria. Seu download público no portal só deve ser exibido caso autorizado expressamente pela secretaria da convenção ou após disponibilização de versão com dados pessoais anonimizados.

## `institutional_settings` (site: dados de contato, redes e rodapé)

Tabela singleton (registro único) gerida em **Sistema → Dados Institucionais** por `super_admin` e `convention_editor`. Substitui os dados anteriormente fixos em `config/cbngo.php` (issue cbn-go/portal-cbngo-site#37).

| Coluna | Descrição | Observação |
|---|---|---|
| `email` | E-mail geral de atendimento | Ex: `contato@cbngo.org.br` |
| `phone` | Telefone fixo oficial | Ex: `(62) 3223-0000`, opcional |
| `whatsapp` | WhatsApp para suporte / secretaria | Ex: `(62) 98100-0000`, opcional |
| `office_hours` | Horário de atendimento | Ex: `Segunda a Sexta, das 08h às 17h` |
| `street`, `number`, `complement` | Logradouro, número e complemento | Endereço oficial do Art. 2º do Estatuto |
| `district`, `city`, `state`, `postal_code` | Bairro, cidade, UF e CEP | Ex: `Setor Central`, `Goiânia`, `GO`, `74020-045` |
| `maps_url` | Link do Google Maps | Usado no botão "Como chegar" no rodapé e página `/contato` |
| `instagram_url` | Perfil no Instagram | Ex: `https://www.instagram.com/cbngoias/` |
| `facebook_url` | Página no Facebook | Ex: `https://www.facebook.com/cbngoias/` |
| `youtube_url` | Canal no YouTube | Ex: `https://www.youtube.com/@cbngoias` |
| `tiktok_url`, `spotify_url`, `linkedin_url` | Outras redes sociais | Opcionais |
| `updated_at` | Data/hora da última alteração | Usar como chave para invalidação de cache no site |

### Regra de consumo e cache no portal público
O portal público consulta as configurações com:
```sql
SELECT * FROM institutional_settings LIMIT 1;
```
- **Campos vazios de redes sociais:** quando a URL for nula ou vazia, o respectivo ícone **não deve ser exibido** no rodapé ou cabeçalho do portal público.
- **Cache:** o site pode cachear esse registro por tempo indeterminado e invalidar o cache sempre que o `updated_at` mudar.

## Mídia compartilhada

Todos os uploads do painel vão para o disco `media`, servido em produção pelo subdomínio `media.cbngo.org.br` (veja `docs/deploy-hostinger-midia.md`).

- **URL final:** `MEDIA_URL` + `/` + caminho salvo no banco. Nunca há URL absoluta no banco para esses campos.
- **Versões WebP:** toda imagem (`featured_image`, itens de `gallery`, `cover_image`, `avatar`, `logo`, `photo`, `banners.image`, `social_projects.cover_image` e itens de `social_projects.gallery`) ganha três versões ao lado do original, geradas logo após o salvamento:

  | Versão | Largura máxima | Exemplo para `news/featured/abc.jpg` |
  |---|---|---|
  | `thumb` | 400 px | `news/featured/abc-thumb.webp` |
  | `card` | 800 px | `news/featured/abc-card.webp` |
  | `cover` | 1600 px | `news/featured/abc-cover.webp` |

  A proporção é mantida e a imagem nunca é ampliada (uma imagem de 600 px gera `card` e `cover` com 600 px). Use as versões em `srcset` e mantenha o original como fallback, porque as versões podem levar alguns segundos para existir após o upload.
- **Documentos** (`documents.file`) não têm versões.
- **Anexos do RichEditor** (`news/attachments`, `articles/attachments`, `projects/attachments`) ficam embutidos no HTML de `content` já com a URL absoluta de `MEDIA_URL`.
- Quando uma imagem é substituída ou o registro é excluído, o painel apaga o original e as versões.

## Dados de desenvolvimento

`php artisan migrate:fresh --seed` em ambiente `local` cria:

- as 8 igrejas polo com slug, coordenadas e horários de culto;
- 3 autores com perfil;
- artigos e notícias em todas as categorias, incluindo rascunhos e conteúdo pendente de revisão;
- avisos vigente, agendado e expirado;
- a diretoria real do triênio 2026–2029 (seeder também usado em produção) e os documentos Estatuto, Regimento Interno e Pacto Renovado (como links);
- banners do hero vigentes (link interno e externo), agendado e expirado, com artes de exemplo em 16:9 criadas no disco de mídia quando ainda não existem;
- 8 projetos sociais publicados (2 por categoria, em igrejas diferentes), um pendente de revisão e um rascunho devolvido para ajustes da igreja de Goiânia.

Veja `Database\Seeders\DevelopmentContentSeeder`.
