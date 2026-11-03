<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class DeleteUser
{
    public function handle(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user): void {
            Role::query()->where('name', 'admin')->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('manage', User::class);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($actor->is($user)) {
                throw ValidationException::withMessages(['user' => 'საკუთარი ანგარიშის წაშლა შეუძლებელია.']);
            }
            if ($user->hasRole('admin', 'web') && User::role('admin', 'web')->count() <= 1) {
                throw ValidationException::withMessages(['user' => 'ბოლო ადმინისტრატორის წაშლა შეუძლებელია. ჯერ სხვა ადმინისტრატორი დანიშნე.']);
            }
            $user->delete();
        }, 3);
    }
}
