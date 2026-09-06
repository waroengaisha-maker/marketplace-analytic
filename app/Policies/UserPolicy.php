<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function manageAccount(User $actor, User $user): bool
    {
        return $actor->isNot($user)
            && ($actor->isSuperAdmin() || ($actor->isAdmin() && ! $user->isAdmin()));
    }

    public function manageRole(User $actor, User $user): bool
    {
        return $actor->isSuperAdmin() && $actor->isNot($user);
    }

    public function createAccount(User $actor, UserRole $role): bool
    {
        return $actor->isSuperAdmin() || ($actor->isAdmin() && $role === UserRole::User);
    }
}
