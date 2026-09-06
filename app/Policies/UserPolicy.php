<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manageAccount(User $actor, User $user): bool
    {
        return $actor->isAdmin() && $actor->isNot($user);
    }
}
