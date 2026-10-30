<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_domain_tables(): void
    {
        $this->assertTrue(Schema::hasTable('churches'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('author_profiles'));
        $this->assertTrue(Schema::hasTable('notices'));
        $this->assertTrue(Schema::hasTable('articles'));
        $this->assertTrue(Schema::hasTable('news'));
        $this->assertTrue(Schema::hasTable('board_members'));
        $this->assertTrue(Schema::hasTable('documents'));
        $this->assertTrue(Schema::hasTable('banners'));
        $this->assertTrue(Schema::hasTable('social_projects'));
        $this->assertTrue(Schema::hasTable('municipalities'));
        $this->assertTrue(Schema::hasTable('municipality_indicators'));
    }

    public function test_churches_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('churches', [
            'id',
            'name',
            'slug',
            'cnpj',
            'registration_number',
            'pastor_name',
            'municipality_id',
            'city',
            'state',
            'neighborhood',
            'zip_code',
            'address',
            'number',
            'complement',
            'latitude',
            'longitude',
            'phone',
            'cellphone',
            'email',
            'social_links',
            'service_times',
            'logo',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('churches', ['city']));
        $this->assertTrue(Schema::hasIndex('churches', ['is_active']));
        $this->assertTrue(Schema::hasIndex('churches', ['slug'], 'unique'));
        $this->assertTrue(Schema::hasIndex('churches', ['is_active', 'city']));
    }

    public function test_users_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'id',
            'name',
            'email',
            'email_verified_at',
            'password',
            'role',
            'church_id',
            'is_active',
            'remember_token',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('users', ['email'], 'unique'));
        $this->assertTrue(Schema::hasIndex('users', ['role']));
        $this->assertTrue(Schema::hasIndex('users', ['church_id']));
        $this->assertTrue(Schema::hasIndex('users', ['is_active']));
        $this->assertForeignKeyOnDelete('users', 'church_id', 'churches', 'set null');
    }

    public function test_author_profiles_columns_and_foreign_key(): void
    {
        $this->assertTrue(Schema::hasColumns('author_profiles', [
            'id',
            'user_id',
            'slug',
            'pastoral_title',
            'bio',
            'avatar',
            'social_links',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('author_profiles', ['user_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('author_profiles', ['slug'], 'unique'));
        $this->assertForeignKeyOnDelete('author_profiles', 'user_id', 'users', 'cascade');
    }

    public function test_notices_columns_indexes_and_foreign_key(): void
    {
        $this->assertTrue(Schema::hasColumns('notices', [
            'id',
            'user_id',
            'title',
            'slug',
            'content',
            'action_url',
            'action_label',
            'priority',
            'starts_at',
            'expires_at',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('notices', ['slug'], 'unique'));
        $this->assertTrue(Schema::hasIndex('notices', ['user_id']));
        $this->assertTrue(Schema::hasIndex('notices', ['priority']));
        $this->assertTrue(Schema::hasIndex('notices', ['starts_at']));
        $this->assertTrue(Schema::hasIndex('notices', ['expires_at']));
        $this->assertTrue(Schema::hasIndex('notices', ['is_active']));
        $this->assertForeignKeyOnDelete('notices', 'user_id', 'users', 'set null');
    }

    public function test_articles_columns_indexes_and_foreign_key(): void
    {
        $this->assertTrue(Schema::hasColumns('articles', [
            'id',
            'user_id',
            'title',
            'slug',
            'category',
            'excerpt',
            'content',
            'reading_time',
            'cover_image',
            'status',
            'published_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('articles', ['slug'], 'unique'));
        $this->assertTrue(Schema::hasIndex('articles', ['user_id']));
        $this->assertTrue(Schema::hasIndex('articles', ['status']));
        $this->assertTrue(Schema::hasIndex('articles', ['published_at']));
        $this->assertTrue(Schema::hasIndex('articles', ['status', 'published_at']));
        $this->assertTrue(Schema::hasIndex('articles', ['category', 'status', 'published_at']));
        $this->assertForeignKeyOnDelete('articles', 'user_id', 'users', 'cascade');
    }

    public function test_news_columns_indexes_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasColumns('news', [
            'id',
            'church_id',
            'user_id',
            'title',
            'slug',
            'category',
            'excerpt',
            'content',
            'featured_image',
            'gallery',
            'event_date',
            'is_official',
            'status',
            'published_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('news', ['slug'], 'unique'));
        $this->assertTrue(Schema::hasIndex('news', ['church_id']));
        $this->assertTrue(Schema::hasIndex('news', ['user_id']));
        $this->assertTrue(Schema::hasIndex('news', ['event_date']));
        $this->assertTrue(Schema::hasIndex('news', ['is_official']));
        $this->assertTrue(Schema::hasIndex('news', ['status']));
        $this->assertTrue(Schema::hasIndex('news', ['published_at']));
        $this->assertTrue(Schema::hasIndex('news', ['status', 'published_at']));
        $this->assertTrue(Schema::hasIndex('news', ['category', 'status', 'published_at']));
        $this->assertForeignKeyOnDelete('news', 'church_id', 'churches', 'cascade');
        $this->assertForeignKeyOnDelete('news', 'user_id', 'users', 'cascade');
    }

    public function test_board_members_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('board_members', [
            'id',
            'name',
            'role',
            'group',
            'region',
            'term_label',
            'photo',
            'position',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('board_members', ['is_active', 'group', 'position']));
        $this->assertTrue(Schema::hasIndex('board_members', ['term_label']));
    }

    public function test_documents_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('documents', [
            'id',
            'title',
            'slug',
            'description',
            'category',
            'file',
            'external_url',
            'file_format',
            'position',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('documents', ['slug'], 'unique'));
        $this->assertTrue(Schema::hasIndex('documents', ['category']));
        $this->assertTrue(Schema::hasIndex('documents', ['is_active', 'position']));
    }

    public function test_banners_columns_indexes_and_foreign_key(): void
    {
        $this->assertTrue(Schema::hasColumns('banners', [
            'id',
            'user_id',
            'title',
            'tag',
            'subtitle',
            'meta',
            'image',
            'alt_text',
            'link_url',
            'link_label',
            'opens_in_new_tab',
            'position',
            'starts_at',
            'ends_at',
            'is_active',
            'is_square',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('banners', ['is_active', 'position']));
        $this->assertTrue(Schema::hasIndex('banners', ['starts_at']));
        $this->assertTrue(Schema::hasIndex('banners', ['ends_at']));
        $this->assertForeignKeyOnDelete('banners', 'user_id', 'users', 'set null');
        $this->assertFalse(Schema::hasColumn('banners', 'focal_point'));
    }

    public function test_social_projects_columns_indexes_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasColumns('social_projects', [
            'id', 'church_id', 'user_id', 'title', 'slug', 'category', 'summary', 'description',
            'cover_image', 'gallery', 'target_audience', 'beneficiaries_count', 'city',
            'started_at', 'ended_at', 'contact_name', 'contact_phone', 'contact_email', 'how_to_help',
            'status', 'published_at', 'is_featured', 'review_notes', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('social_projects', ['slug'], 'unique'));
        $this->assertTrue(Schema::hasIndex('social_projects', ['status', 'published_at']));
        $this->assertTrue(Schema::hasIndex('social_projects', ['category', 'status', 'published_at']));
        $this->assertTrue(Schema::hasIndex('social_projects', ['city']));
        $this->assertTrue(Schema::hasIndex('social_projects', ['is_featured']));
        $this->assertForeignKeyOnDelete('social_projects', 'church_id', 'churches', 'cascade');
        $this->assertForeignKeyOnDelete('social_projects', 'user_id', 'users', 'set null');
    }

    public function test_municipalities_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('municipalities', [
            'id',
            'ibge_code',
            'name',
            'slug',
            'normalized_name',
            'immediate_region_code',
            'immediate_region_name',
            'intermediate_region_code',
            'intermediate_region_name',
            'micro_region_name',
            'meso_region_name',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('municipalities', ['ibge_code'], 'unique'));
        $this->assertTrue(Schema::hasIndex('municipalities', ['slug'], 'unique'));
        $this->assertTrue(Schema::hasIndex('municipalities', ['normalized_name']));
        $this->assertTrue(Schema::hasIndex('municipalities', ['immediate_region_code']));
        $this->assertTrue(Schema::hasIndex('municipalities', ['intermediate_region_code']));

        $this->assertForeignKeyOnDelete('churches', 'municipality_id', 'municipalities', 'set null');
    }

    public function test_municipality_indicators_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('municipality_indicators', [
            'id',
            'municipality_id',
            'indicator',
            'period',
            'value',
            'source',
            'fetched_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('municipality_indicators', ['municipality_id', 'indicator', 'period'], 'unique'));
        $this->assertTrue(Schema::hasIndex('municipality_indicators', ['indicator', 'period']));
        $this->assertForeignKeyOnDelete('municipality_indicators', 'municipality_id', 'municipalities', 'cascade');
    }

    private function assertForeignKeyOnDelete(string $table, string $column, string $references, string $onDelete): void
    {
        /** @var list<array{columns: list<string>, foreign_table: string, on_delete: string}> $foreignKeys */
        $foreignKeys = Schema::getForeignKeys($table);

        $match = null;

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey['columns'] === [$column] && $foreignKey['foreign_table'] === $references) {
                $match = $foreignKey;
                break;
            }
        }

        $this->assertNotNull($match);
        $this->assertIsArray($match);
        $this->assertSame($onDelete, $match['on_delete']);
    }
}
