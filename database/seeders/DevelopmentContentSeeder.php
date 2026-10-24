<?php

namespace Database\Seeders;

use App\Enums\ArticleCategory;
use App\Enums\BoardGroup;
use App\Enums\DocumentCategory;
use App\Enums\NewsCategory;
use App\Enums\NoticePriority;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\BoardMember;
use App\Models\Church;
use App\Models\Document;
use App\Models\News;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Conteúdo realista de desenvolvimento para todas as tabelas consumidas pelo
 * portal público. Idempotente: pode ser executado várias vezes sem duplicar.
 */
class DevelopmentContentSeeder extends Seeder
{
    public const EDITOR_EMAIL = 'comunicacao@cbngo.org.br';

    public const REPRESENTATIVE_EMAIL = 'secretaria.goiania@cbngo.org.br';

    /**
     * @var list<array{name: string, email: string, pastoral_title: string, bio: string, social_links: array<string, string>}>
     */
    public const AUTHORS = [
        [
            'name' => 'Marcos Vinícius Andrade',
            'email' => 'marcos.andrade@cbngo.org.br',
            'pastoral_title' => 'Presidente da CBN-GO',
            'bio' => 'Pastor há mais de vinte anos, mestre em Teologia e presidente da Convenção Batista Nacional do Estado de Goiás.',
            'social_links' => [
                'instagram' => 'https://instagram.com/cbngoias',
                'website' => 'https://cbngo.org.br',
            ],
        ],
        [
            'name' => 'Débora Lima Rocha',
            'email' => 'debora.rocha@cbngo.org.br',
            'pastoral_title' => 'Coordenadora de Missões',
            'bio' => 'Missionária e pastora, coordena as frentes missionárias da convenção no interior de Goiás.',
            'social_links' => [
                'instagram' => 'https://instagram.com/missoescbngo',
                'youtube' => 'https://youtube.com/@missoescbngo',
            ],
        ],
        [
            'name' => 'Samuel Ferreira Costa',
            'email' => 'samuel.costa@cbngo.org.br',
            'pastoral_title' => 'Professor do SETEBAN-GO',
            'bio' => 'Doutor em Teologia Sistemática e professor do Seminário Teológico Batista Nacional em Goiás.',
            'social_links' => [
                'linkedin' => 'https://linkedin.com/in/samuelfcosta',
            ],
        ],
    ];

    public function run(): void
    {
        $this->seedStaff();
        $authors = $this->seedAuthors();
        $this->seedArticles($authors);
        $this->seedNews();
        $this->seedNotices();
        $this->seedBoardMembers();
        $this->seedDocuments();
    }

    private function seedStaff(): void
    {
        User::query()->updateOrCreate(
            ['email' => self::EDITOR_EMAIL],
            [
                'name' => 'Equipe de Comunicação CBN-GO',
                'password' => 'password',
                'role' => UserRole::CONVENTION_EDITOR,
                'church_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => self::REPRESENTATIVE_EMAIL],
            [
                'name' => 'Secretaria IBN Goiânia',
                'password' => 'password',
                'role' => UserRole::CHURCH_REPRESENTATIVE,
                'church_id' => $this->church('Goiânia')?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * @return list<User>
     */
    private function seedAuthors(): array
    {
        $authors = [];

        foreach (self::AUTHORS as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'role' => UserRole::AUTHOR,
                    'church_id' => null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->authorProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'pastoral_title' => $data['pastoral_title'],
                    'bio' => $data['bio'],
                    'social_links' => $data['social_links'],
                ],
            );

            $authors[] = $user;
        }

        return $authors;
    }

    /**
     * @param  list<User>  $authors
     */
    private function seedArticles(array $authors): void
    {
        $articles = [
            [
                'author' => 0,
                'title' => 'Avivamento e fidelidade bíblica em Goiás',
                'slug' => 'avivamento-e-fidelidade-biblica-em-goias',
                'category' => ArticleCategory::TEOLOGIA,
                'excerpt' => 'Como a igreja pode manter a chama do Espírito aliada à fidelidade doutrinária e ao compromisso evangelístico.',
                'paragraphs' => 9,
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now()->subDays(12),
            ],
            [
                'author' => 1,
                'title' => 'Novas frentes missionárias no sudoeste goiano',
                'slug' => 'novas-frentes-missionarias-no-sudoeste-goiano',
                'category' => ArticleCategory::MISSOES,
                'excerpt' => 'Relato das primeiras plantações de igrejas em municípios ainda não alcançados da região de Rio Verde e Jataí.',
                'paragraphs' => 6,
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now()->subDays(8),
            ],
            [
                'author' => 0,
                'title' => 'O cuidado pastoral com a vida do próprio pastor',
                'slug' => 'o-cuidado-pastoral-com-a-vida-do-proprio-pastor',
                'category' => ArticleCategory::LIDERANCA,
                'excerpt' => 'Princípios para preservar a saúde física, emocional e espiritual no exercício do ministério.',
                'paragraphs' => 5,
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now()->subDays(5),
            ],
            [
                'author' => 2,
                'title' => 'O culto doméstico e o discipulado das novas gerações',
                'slug' => 'o-culto-domestico-e-o-discipulado-das-novas-geracoes',
                'category' => ArticleCategory::FAMILIA,
                'excerpt' => 'Resgatando o altar familiar como primeiro espaço de formação cristã de crianças e adolescentes.',
                'paragraphs' => 4,
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now()->subDays(2),
            ],
            [
                'author' => 2,
                'title' => 'Discipulado em pequenos grupos: um roteiro prático',
                'slug' => 'discipulado-em-pequenos-grupos-um-roteiro-pratico',
                'category' => ArticleCategory::DISCIPULADO,
                'excerpt' => 'Rascunho em revisão pela equipe de comunicação.',
                'paragraphs' => 3,
                'status' => PublishStatus::PENDING_REVIEW,
                'published_at' => null,
            ],
            [
                'author' => 1,
                'title' => 'Devocional: perseverar na oração',
                'slug' => 'devocional-perseverar-na-oracao',
                'category' => ArticleCategory::DEVOCIONAL,
                'excerpt' => 'Rascunho ainda não enviado para revisão.',
                'paragraphs' => 2,
                'status' => PublishStatus::DRAFT,
                'published_at' => null,
            ],
        ];

        foreach ($articles as $data) {
            Article::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'user_id' => $authors[$data['author']]->id,
                    'title' => $data['title'],
                    'category' => $data['category'],
                    'excerpt' => $data['excerpt'],
                    'content' => $this->content($data['title'], $data['paragraphs']),
                    'status' => $data['status'],
                    'published_at' => $data['published_at'],
                ],
            );
        }
    }

    private function seedNews(): void
    {
        $editor = User::query()->where('email', self::EDITOR_EMAIL)->firstOrFail();
        $representative = User::query()->where('email', self::REPRESENTATIVE_EMAIL)->firstOrFail();

        $news = [
            [
                'title' => 'Diretoria da CBN-GO define calendário de assembleias',
                'slug' => 'diretoria-da-cbn-go-define-calendario-de-assembleias',
                'category' => NewsCategory::COMUNICADO_OFICIAL,
                'church' => null,
                'is_official' => true,
                'event_date' => null,
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now()->subDays(10),
            ],
            [
                'title' => 'IBN Rio Verde inaugura nova frente missionária',
                'slug' => 'ibn-rio-verde-inaugura-nova-frente-missionaria',
                'category' => NewsCategory::MISSOES,
                'church' => 'Rio Verde',
                'is_official' => false,
                'event_date' => now()->subDays(7)->toDateString(),
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now()->subDays(6),
            ],
            [
                'title' => 'SETEBAN-GO abre inscrições para o vestibular',
                'slug' => 'seteban-go-abre-inscricoes-para-o-vestibular',
                'category' => NewsCategory::EDUCACAO_TEOLOGICA,
                'church' => null,
                'is_official' => true,
                'event_date' => null,
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now()->subDays(4),
            ],
            [
                'title' => 'Conferência estadual de jovens reúne igrejas em Anápolis',
                'slug' => 'conferencia-estadual-de-jovens-reune-igrejas-em-anapolis',
                'category' => NewsCategory::EVENTOS,
                'church' => 'Anápolis',
                'is_official' => false,
                'event_date' => now()->addDays(20)->toDateString(),
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now()->subDay(),
            ],
            [
                'title' => 'Campanha do agasalho da IBN Goiânia arrecada 2 mil peças',
                'slug' => 'campanha-do-agasalho-da-ibn-goiania-arrecada-2-mil-pecas',
                'category' => NewsCategory::ACAO_SOCIAL,
                'church' => 'Goiânia',
                'is_official' => false,
                'event_date' => null,
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now()->subHours(6),
            ],
            [
                'title' => 'Encontro de casais da IBN Goiânia (rascunho)',
                'slug' => 'encontro-de-casais-da-ibn-goiania',
                'category' => NewsCategory::GERAL,
                'church' => 'Goiânia',
                'is_official' => false,
                'event_date' => now()->addDays(40)->toDateString(),
                'status' => PublishStatus::DRAFT,
                'published_at' => null,
            ],
        ];

        foreach ($news as $data) {
            $church = $data['church'] !== null ? $this->church($data['church']) : null;

            News::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'church_id' => $church?->id,
                    'user_id' => $data['church'] === 'Goiânia' ? $representative->id : $editor->id,
                    'title' => $data['title'],
                    'category' => $data['category'],
                    'excerpt' => "Resumo: {$data['title']}.",
                    'content' => $this->content($data['title'], 3),
                    'event_date' => $data['event_date'],
                    'is_official' => $data['is_official'],
                    'status' => $data['status'],
                    'published_at' => $data['published_at'],
                ],
            );
        }
    }

    private function seedNotices(): void
    {
        $editor = User::query()->where('email', self::EDITOR_EMAIL)->firstOrFail();

        $notices = [
            [
                'slug' => 'inscricoes-assembleia-estadual',
                'title' => 'Inscrições abertas para a Assembleia Estadual',
                'content' => '<p>As inscrições de mensageiros para a Assembleia Estadual seguem até o fim do mês.</p>',
                'action_url' => 'https://cbngo.org.br/assembleia',
                'action_label' => 'Inscreva-se',
                'priority' => NoticePriority::URGENT,
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addDays(30),
            ],
            [
                'slug' => 'retiro-de-pastores',
                'title' => 'Retiro de pastores e esposas',
                'content' => '<p>O retiro anual de pastores acontece no próximo trimestre. Aguarde a abertura das vagas.</p>',
                'action_url' => null,
                'action_label' => null,
                'priority' => NoticePriority::HIGH,
                'starts_at' => now()->addDays(15),
                'expires_at' => now()->addDays(60),
            ],
            [
                'slug' => 'recesso-secretaria',
                'title' => 'Recesso da secretaria estadual',
                'content' => '<p>A secretaria esteve fechada para recesso administrativo.</p>',
                'action_url' => null,
                'action_label' => null,
                'priority' => NoticePriority::NORMAL,
                'starts_at' => now()->subDays(40),
                'expires_at' => now()->subDays(20),
            ],
        ];

        foreach ($notices as $data) {
            Notice::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [...$data, 'user_id' => $editor->id, 'is_active' => true],
            );
        }
    }

    /**
     * Diretoria da gestão atual, na mesma composição usada pelo portal público.
     */
    private function seedBoardMembers(): void
    {
        $members = [
            ['name' => 'Pr. Roberto Silveira', 'role' => 'Presidente', 'group' => BoardGroup::PRESIDENCIA],
            ['name' => 'Pr. Carlos Eduardo Mendes', 'role' => '1º Vice-presidente', 'group' => BoardGroup::VICE_PRESIDENCIA],
            ['name' => 'Pr. André Luiz Barbosa', 'role' => '2º Vice-presidente', 'group' => BoardGroup::VICE_PRESIDENCIA],
            ['name' => 'Pra. Helena Albuquerque', 'role' => '1ª Secretária', 'group' => BoardGroup::SECRETARIA],
            ['name' => 'Pr. Daniel Ferreira Costa', 'role' => '2º Secretário', 'group' => BoardGroup::SECRETARIA],
            ['name' => 'Diác. Márcio Antunes', 'role' => '1º Tesoureiro', 'group' => BoardGroup::TESOURARIA],
            ['name' => 'Presb. Juliana Rezende', 'role' => '2ª Tesoureira', 'group' => BoardGroup::TESOURARIA],
            ['name' => 'Pr. Valdir Cunha', 'role' => 'Relator', 'group' => BoardGroup::CONSELHO_FISCAL],
            ['name' => 'Pr. Samuel Rocha', 'role' => 'Vogal', 'group' => BoardGroup::CONSELHO_FISCAL],
            ['name' => 'Pr. Elias Moreira', 'role' => 'Vogal', 'group' => BoardGroup::CONSELHO_FISCAL],
            ['name' => 'Pr. Fernando Lopes', 'role' => 'Coordenador Regional', 'group' => BoardGroup::COORDENACAO_REGIONAL, 'region' => 'Região Metropolitana de Goiânia'],
            ['name' => 'Pr. Gilberto Nunes', 'role' => 'Coordenador Regional', 'group' => BoardGroup::COORDENACAO_REGIONAL, 'region' => 'Região Centro (Anápolis)'],
            ['name' => 'Pr. Wesley Prado', 'role' => 'Coordenador Regional', 'group' => BoardGroup::COORDENACAO_REGIONAL, 'region' => 'Região Sudoeste (Rio Verde e Jataí)'],
            ['name' => 'Pr. Josué Teixeira', 'role' => 'Coordenador Regional', 'group' => BoardGroup::COORDENACAO_REGIONAL, 'region' => 'Região Entorno do DF (Luziânia)'],
        ];

        foreach ($members as $index => $data) {
            BoardMember::query()->updateOrCreate(
                ['name' => $data['name']],
                [...$data, 'term_label' => 'Gestão 2025–2027', 'position' => $index + 1, 'is_active' => true],
            );
        }
    }

    private function seedDocuments(): void
    {
        $documents = [
            [
                'slug' => 'estatuto-da-cbn',
                'title' => 'Estatuto da CBN',
                'description' => 'Estatuto social vigente da Convenção Batista Nacional: finalidades, órgãos deliberativos e administrativos, recursos e reforma estatutária.',
                'category' => DocumentCategory::ESTATUTO,
                'external_url' => 'https://cbngo.com.br/dados-abertos/estatuto.pdf',
                'position' => 1,
            ],
            [
                'slug' => 'regimento-interno-da-cbn',
                'title' => 'Regimento Interno da CBN',
                'description' => 'Normas de filiação, direitos e deveres das igrejas, funcionamento da Assembleia Geral, do Conplex e das convenções estaduais.',
                'category' => DocumentCategory::REGIMENTO,
                'external_url' => 'https://cbngo.com.br/dados-abertos/regimento-interno.pdf',
                'position' => 2,
            ],
            [
                'slug' => 'pacto-renovado',
                'title' => 'Pacto Renovado',
                'description' => 'Pacto firmado pelos batistas nacionais em Luziânia-GO: sonho, visão, perdão, conduta, compromissos administrativos e futuro.',
                'category' => DocumentCategory::PACTO,
                'external_url' => 'https://cbngo.com.br/pacto-renovado/',
                'position' => 3,
            ],
        ];

        foreach ($documents as $data) {
            Document::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [...$data, 'file' => null, 'is_active' => true],
            );
        }
    }

    private function church(string $city): ?Church
    {
        return Church::query()->where('name', "Igreja Batista Nacional de {$city}")->first();
    }

    /**
     * Monta um texto HTML com o número de parágrafos informado.
     */
    private function content(string $title, int $paragraphs): string
    {
        $base = [
            'A Convenção Batista Nacional do Estado de Goiás segue firme no propósito de anunciar o Evangelho de Jesus Cristo em cada cidade do estado, fortalecendo as igrejas locais e formando novos líderes para o Reino.',
            'Em tempos de muitas vozes e pouca escuta, as igrejas são chamadas a voltar às Escrituras com humildade, oração e disposição para servir, cuidando umas das outras e alcançando os que ainda não conhecem a fé.',
            'Pastores, líderes e membros têm compartilhado testemunhos de vidas transformadas, famílias restauradas e comunidades inteiras alcançadas pelo amor de Deus, fruto do trabalho perseverante de cada congregação.',
            'O compromisso com a formação teológica séria, o discipulado intencional e a ação social responsável continua sendo uma marca das igrejas filiadas, que trabalham em unidade para a glória de Deus.',
        ];

        $html = "<h2>{$title}</h2>";

        for ($i = 0; $i < $paragraphs; $i++) {
            $html .= '<p>'.$base[$i % count($base)].'</p>';
        }

        return $html;
    }
}
