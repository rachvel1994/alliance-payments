<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class SaveUser
{
    public function handle(array $data, User $actor, ?User $user = null): User
    {
        try {
            return DB::transaction(function () use ($data, $actor, $user): User {
                Role::query()->where('name', 'admin')->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                Gate::forUser($actor)->authorize('manage', User::class);
                $user = $user === null ? new User : User::query()->lockForUpdate()->findOrFail($user->id);
                $role = $data['role'] ?? null;
                $permissions = $data['permissions'];

                $this->ensureAccessCanBeChanged($user, $actor, $role, $permissions);

                $user->fill(['name' => $data['name'], 'email' => $data['email']]);
                if (! $user->exists) {
                    $user->password = $data['password'];
                }
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }
                $user->save();
                $this->assignAccess($user, $role, $permissions);

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'ამ ელ. ფოსტით მომხმარებელი უკვე არსებობს.']);
        }
    }

    private function ensureAccessCanBeChanged(User $user, User $actor, ?string $role, array $permissions): void
    {
        if (! $user->exists) {
            return;
        }

        if ($user->is($actor)) {
            $currentPermissions = $user->permissions->pluck('name')->sort()->values()->all();
            $requestedPermissions = collect($permissions)->sort()->values()->all();

            if ($role !== 'admin' || $currentPermissions !== $requestedPermissions) {
                throw ValidationException::withMessages([
                    'role' => 'საკუთარი როლისა და უფლებების შეცვლა ამ გვერდიდან შეუძლებელია.',
                ]);
            }
        }

        $removesAdminRole = $user->hasRole('admin', 'web') && $role !== 'admin';
        if ($removesAdminRole && User::role('admin', 'web')->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'ბოლო ადმინისტრატორის როლის შეცვლა შეუძლებელია.']);
        }
    }

    private function assignAccess(User $user, ?string $role, array $permissions): void
    {
        if ($role !== null) {
            $inheritedPermissions = Role::findByName($role, 'web')->permissions->pluck('name')->all();
            $permissions = array_values(array_diff($permissions, $inheritedPermissions));
        }

        $user->syncRoles($role === null ? [] : [$role]);
        $user->syncPermissions($permissions);
    }
}
