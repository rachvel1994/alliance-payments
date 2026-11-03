<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class UserHome
{
    public function path(User $user): string
    {
        foreach (['overview.view' => '/dashboard', 'clients.view' => '/clients', 'apartments.view' => '/apartments',
            'contracts.view' => '/contracts', 'payments.view' => '/payments', 'imports.view' => '/imports'] as $permission => $path) {
            if ($user->can($permission)) {
                return $path;
            }
        }

        return '/profile';
    }
}
