<?php

namespace App\Support;

use App\Models\Article;
use App\Models\AuthorProfile;
use App\Models\Banner;
use App\Models\BoardMember;
use App\Models\Church;
use App\Models\Document;
use App\Models\News;

/**
 * Lista os arquivos de mídia referenciados pelo banco, usada pelos comandos
 * de migração de disco e de geração de versões WebP.
 */
class MediaInventory
{
    /**
     * Models com imagens que ganham versões WebP.
     *
     * @var list<class-string<News|Article|AuthorProfile|Church|BoardMember|Banner>>
     */
    public const IMAGE_MODELS = [
        News::class,
        Article::class,
        AuthorProfile::class,
        Church::class,
        BoardMember::class,
        Banner::class,
    ];

    /**
     * Diretórios dos anexos do RichEditor, que ficam embutidos no HTML do conteúdo.
     *
     * @var list<string>
     */
    public const ATTACHMENT_DIRECTORIES = [
        'news/attachments',
        'articles/attachments',
    ];

    /**
     * @return list<string>
     */
    public static function imagePaths(): array
    {
        $paths = [];

        foreach (self::IMAGE_MODELS as $modelClass) {
            $modelClass::query()->withoutGlobalScopes()->lazyById()->each(
                function (News|Article|AuthorProfile|Church|BoardMember|Banner $model) use (&$paths): void {
                    array_push($paths, ...$model->currentImagePaths());
                },
            );
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    public static function documentPaths(): array
    {
        /** @var list<string> $paths */
        $paths = Document::query()->whereNotNull('file')->pluck('file')->all();

        return $paths;
    }
}
