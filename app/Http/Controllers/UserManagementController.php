<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Users\DeleteUser;
use App\Actions\Users\SaveUser;
use App\Http\Requests\SaveUserRequest;
use App\Http\Requests\UserIndexRequest;
use App\Models\User;
use App\Queries\UserManagementQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserManagementController extends Controller
{
    public function index(UserIndexRequest $request, UserManagementQuery $query): Response
    {
        return Inertia::render('Admin/Users/Index', $query->index($request->validated()));
    }

    public function create(Request $request, UserManagementQuery $query): Response
    {
        return Inertia::render('Admin/Users/Form', $query->form(null, $request->user()));
    }

    public function edit(Request $request, User $user, UserManagementQuery $query): Response
    {
        return Inertia::render('Admin/Users/Form', $query->form($user, $request->user()));
    }

    public function store(SaveUserRequest $request, SaveUser $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user());

        return to_route('admin.users.index')->with('success', 'მომხმარებელი დაემატა.');
    }

    public function update(SaveUserRequest $request, User $user, SaveUser $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user(), $user);

        return to_route('admin.users.index')->with('success', 'მომხმარებელი და უფლებები განახლდა.');
    }

    public function destroy(Request $request, User $user, DeleteUser $delete): RedirectResponse
    {
        $delete->handle($request->user(), $user);

        return to_route('admin.users.index')->with('success', 'მომხმარებელი წაიშალა.');
    }
}
