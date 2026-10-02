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
    }

    public function test_churches_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('churches', [
            'id',
            'name',
            'cnpj',
            'registration_number',
            'pastor_name',
            'city',
            'state',
            'neighborhood',
            'zip_code',
            'address',
            'number',
            'complement',
            'phone',
            'cellphone',
            'email',
            'social_links',
            'logo',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('churches', ['city']));
        $this->assertTrue(Schema::hasIndex('churches', ['is_active']));
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
            'pastoral_title',
            'bio',
            'avatar',
            'social_links',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasIndex('author_profiles', ['user_id'], 'unique'));
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
            'excerpt',
            'content',
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
        $this->assertForeignKeyOnDelete('news', 'church_id', 'churches', 'cascade');
        $this->assertForeignKeyOnDelete('news', 'user_id', 'users', 'cascade');
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
