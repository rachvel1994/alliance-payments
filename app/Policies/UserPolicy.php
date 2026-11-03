<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('admin', 'web') && $user->can('users.manage');
    }
}
