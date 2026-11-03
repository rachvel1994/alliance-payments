<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

final class MakeAdministrator extends Command
{
    protected $signature = 'users:admin {email : არსებული მომხმარებლის ელ. ფოსტა}';

    protected $description = 'არსებული მომხმარებლისთვის ადმინისტრატორის როლის მინიჭება';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        $role = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();
        if ($user === null) {
            $this->error('ამ ელ. ფოსტით მომხმარებელი ვერ მოიძებნა.');

            return self::FAILURE;
        }
        if ($role === null) {
            $this->error('ჯერ გაუშვი RolesAndPermissionsSeeder.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user, $role): void {
            Role::query()->lockForUpdate()->findOrFail($role->id);
            User::query()->lockForUpdate()->findOrFail($user->id)->syncRoles(['admin']);
        }, 3);
        $this->info('ადმინისტრატორის როლი მიენიჭა: '.$user->email);

        return self::SUCCESS;
    }
}
