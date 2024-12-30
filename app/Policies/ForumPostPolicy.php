<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ForumPostPolicy
{
    use HandlesAuthorization;

    public function postForum(User $user): bool
    {
        return $user->hasPermissionTo(Permission::POST_FORUM);
    }
}
