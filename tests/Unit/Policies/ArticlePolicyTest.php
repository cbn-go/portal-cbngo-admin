<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;
use App\Policies\ArticlePolicy;
use PHPUnit\Framework\TestCase;

class ArticlePolicyTest extends TestCase
{
    private ArticlePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ArticlePolicy;
    }

    private function makeUser(UserRole $role, bool $isActive = true, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $isActive;

        return $user;
    }

    private function makeArticle(int $userId = 1): Article
    {
        $article = new Article;
        $article->user_id = $userId;

        return $article;
    }

    public function test_author_can_manage_only_own_articles(): void
    {
        $author = $this->makeUser(UserRole::AUTHOR, true, 10);
        $ownArticle = $this->makeArticle(10);
        $otherArticle = $this->makeArticle(99);

        $this->assertTrue($this->policy->viewAny($author));
        $this->assertTrue($this->policy->create($author));

        $this->assertTrue($this->policy->view($author, $ownArticle));
        $this->assertFalse($this->policy->view($author, $otherArticle));

        $this->assertTrue($this->policy->update($author, $ownArticle));
        $this->assertFalse($this->policy->update($author, $otherArticle));

        $this->assertTrue($this->policy->delete($author, $ownArticle));
        $this->assertFalse($this->policy->delete($author, $otherArticle));

        $this->assertFalse($this->policy->restore($author, $ownArticle));
        $this->assertFalse($this->policy->forceDelete($author, $ownArticle));
    }

    public function test_super_admin_and_convention_editor_can_manage_all_articles(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);
        $editor = $this->makeUser(UserRole::CONVENTION_EDITOR, true, 2);
        $article = $this->makeArticle(10);

        foreach ([$superAdmin, $editor] as $moderator) {
            $this->assertTrue($this->policy->viewAny($moderator));
            $this->assertTrue($this->policy->view($moderator, $article));
            $this->assertTrue($this->policy->create($moderator));
            $this->assertTrue($this->policy->update($moderator, $article));
            $this->assertTrue($this->policy->delete($moderator, $article));
            $this->assertTrue($this->policy->restore($moderator, $article));
            $this->assertTrue($this->policy->forceDelete($moderator, $article));
        }
    }

    public function test_church_representative_cannot_manage_articles(): void
    {
        $churchRep = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, true, 5);
        $article = $this->makeArticle(5);

        $this->assertFalse($this->policy->viewAny($churchRep));
        $this->assertFalse($this->policy->view($churchRep, $article));
        $this->assertFalse($this->policy->create($churchRep));
        $this->assertFalse($this->policy->update($churchRep, $article));
        $this->assertFalse($this->policy->delete($churchRep, $article));
    }

    public function test_inactive_users_are_denied_via_before_hook(): void
    {
        $inactiveAuthor = $this->makeUser(UserRole::AUTHOR, false, 10);
        $inactiveAdmin = $this->makeUser(UserRole::SUPER_ADMIN, false, 1);

        foreach ([$inactiveAuthor, $inactiveAdmin] as $inactiveUser) {
            $this->assertFalse($this->policy->before($inactiveUser, 'viewAny'));
            $this->assertFalse($this->policy->before($inactiveUser, 'update'));
        }
    }
}
