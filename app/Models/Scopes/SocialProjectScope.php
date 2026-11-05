<?php

namespace App\Models\Scopes;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class SocialProjectScope implements Scope
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

        if ($user->role === UserRole::CHURCH_REPRESENTATIVE) {
            $churchId = $user->contentChurchId();

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($model->getTable().'.church_id', $churchId);

            return;
        }

        if ($user->role === UserRole::MINISTER) {
            $table = $model->getTable();
            $churchId = $user->contentChurchId();

            if ($churchId === null) {
                $builder->where($table.'.status', PublishStatus::PUBLISHED);

                return;
            }

            $builder->where(function (Builder $query) use ($table, $churchId): void {
                $query->where($table.'.status', PublishStatus::PUBLISHED)
                    ->orWhere($table.'.church_id', $churchId);
            });

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
