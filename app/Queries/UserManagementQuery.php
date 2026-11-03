<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

final class UserManagementQuery
{
    public function index(array $filters): array
    {
        $query = User::query()->select(['id', 'name', 'email'])->with('roles:id,name');
        if (($term = $filters['q'] ?? '') !== '') {
            $query->where(fn (Builder $q) => $q->whereLike('name', '%'.$term.'%')->orWhereLike('email', '%'.$term.'%'));
        }
        if (isset($filters['role'])) {
            $query->whereHas('roles', fn (Builder $q) => $q->where('name', $filters['role'])->where('guard_name', 'web'));
        }

        return ['filters' => $filters, 'roles' => config('access.roles'),
            'users' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString()->through(fn (User $user): array => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
            ])];
    }

    public function form(?User $user, User $actor): array
    {
        $user?->load(['roles:id,name,guard_name', 'permissions:id,name']);
        $roles = Role::query()->where('guard_name', 'web')->whereIn('name', array_keys(config('access.roles')))
            ->with('permissions:id,name')->get()->map(fn (Role $role): array => [
                'value' => $role->name, 'label' => config('access.roles.'.$role->name), 'permissions' => $role->permissions->pluck('name')->all(),
            ])->all();

        return ['user' => $user === null ? null : [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'role' => $user->hasRole('admin', 'web') ? 'admin' : $user->roles->first()?->name,
            'permissions' => $user->permissions->pluck('name')->all(),
        ], 'isSelf' => $user?->is($actor) ?? false, 'roles' => $roles,
            'permissionGroups' => collect(config('access.permissions'))->except('users.manage')
                ->map(fn (array $permission, string $name): array => ['name' => $name, ...$permission])
                ->groupBy('group')->map(fn ($permissions, string $label): array => ['label' => $label, 'permissions' => $permissions->values()->all()])
                ->values()->all()];
    }
}
