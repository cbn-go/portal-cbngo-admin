<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\News;
use App\Models\User;
use App\Policies\NewsPolicy;
use PHPUnit\Framework\TestCase;

class NewsPolicyTest extends TestCase
{
    private NewsPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new NewsPolicy;
    }

    private function makeUser(UserRole $role, bool $isActive = true, int $id = 1, ?int $churchId = null): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $isActive;
        $user->church_id = $churchId;

        return $user;
    }

    private function makeNews(?int $churchId = null, int $userId = 1): News
    {
        $news = new News;
        $news->church_id = $churchId;
        $news->user_id = $userId;

        return $news;
    }

    public function test_church_representative_can_manage_only_own_church_news(): void
    {
        $churchRep = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, true, 10, 5);
        $ownChurchNews = $this->makeNews(5, 10);
        $otherChurchNews = $this->makeNews(8, 20);

        $this->assertTrue($this->policy->viewAny($churchRep));
        $this->assertTrue($this->policy->create($churchRep));

        $this->assertTrue($this->policy->view($churchRep, $ownChurchNews));
        $this->assertFalse($this->policy->view($churchRep, $otherChurchNews));

        $this->assertTrue($this->policy->update($churchRep, $ownChurchNews));
        $this->assertFalse($this->policy->update($churchRep, $otherChurchNews));

        $this->assertTrue($this->policy->delete($churchRep, $ownChurchNews));
        $this->assertFalse($this->policy->delete($churchRep, $otherChurchNews));

        $this->assertFalse($this->policy->restore($churchRep, $ownChurchNews));
        $this->assertFalse($this->policy->forceDelete($churchRep, $ownChurchNews));
    }

    public function test_church_representative_without_church_is_denied(): void
    {
        $unlinkedRep = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, true, 10, null);
        $someNews = $this->makeNews(5, 10);

        $this->assertFalse($this->policy->viewAny($unlinkedRep));
        $this->assertFalse($this->policy->create($unlinkedRep));
        $this->assertFalse($this->policy->view($unlinkedRep, $someNews));
        $this->assertFalse($this->policy->update($unlinkedRep, $someNews));
        $this->assertFalse($this->policy->delete($unlinkedRep, $someNews));
    }

    public function test_super_admin_and_convention_editor_can_manage_all_news(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);
        $editor = $this->makeUser(UserRole::CONVENTION_EDITOR, true, 2);
        $news = $this->makeNews(5, 10);

        foreach ([$superAdmin, $editor] as $moderator) {
            $this->assertTrue($this->policy->viewAny($moderator));
            $this->assertTrue($this->policy->view($moderator, $news));
            $this->assertTrue($this->policy->create($moderator));
            $this->assertTrue($this->policy->update($moderator, $news));
            $this->assertTrue($this->policy->delete($moderator, $news));
            $this->assertTrue($this->policy->restore($moderator, $news));
            $this->assertTrue($this->policy->forceDelete($moderator, $news));
        }
    }

    public function test_author_cannot_manage_news(): void
    {
        $author = $this->makeUser(UserRole::AUTHOR, true, 15);
        $news = $this->makeNews(5, 15);

        $this->assertFalse($this->policy->viewAny($author));
        $this->assertFalse($this->policy->view($author, $news));
        $this->assertFalse($this->policy->create($author));
        $this->assertFalse($this->policy->update($author, $news));
        $this->assertFalse($this->policy->delete($author, $news));
    }

    public function test_inactive_users_are_denied_all_news_actions(): void
    {
        $inactiveRep = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, false, 10, 5);
        $inactiveAdmin = $this->makeUser(UserRole::SUPER_ADMIN, false, 1);
        $news = $this->makeNews(5, 10);

        foreach ([$inactiveRep, $inactiveAdmin] as $inactiveUser) {
            $this->assertFalse($this->policy->viewAny($inactiveUser));
            $this->assertFalse($this->policy->view($inactiveUser, $news));
            $this->assertFalse($this->policy->create($inactiveUser));
            $this->assertFalse($this->policy->update($inactiveUser, $news));
            $this->assertFalse($this->policy->delete($inactiveUser, $news));
        }
    }
}
