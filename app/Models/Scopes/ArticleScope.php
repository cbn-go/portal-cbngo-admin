<?php

namespace App\Models\Scopes;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ArticleScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        if (! $user->is_active) {
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($user->role->hasAdminPanelAccess()) {
            return;
        }

        if ($user->role === UserRole::AUTHOR) {
            $builder->where($model->getTable().'.user_id', $user->id);

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
