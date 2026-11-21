<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProvisionLocalAdmin extends Command
{
    protected $signature = '48source:admin {--email=admin@48source.test : Administrator email}';

    protected $description = 'Create or reset a local 48Source administrator using a chosen password';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is available only in local and testing environments.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->option('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Enter a valid administrator email.');

            return self::FAILURE;
        }

        $role = Role::where('name', 'admin')->first();

        if (! $role) {
            $this->error('Admin role not found. Run: php artisan db:seed --class=RolesAndPermissionsSeeder');

            return self::FAILURE;
        }

        $password = $this->secret('Choose a new administrator password (minimum 16 characters)');
        $confirmation = $this->secret('Confirm administrator password');

        if (! is_string($password) || strlen($password) < 16 || ! hash_equals($password, (string) $confirmation)) {
            $this->error('Password must be at least 16 characters and match the confirmation.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?: '48Source Administrator';
        $user->password = Hash::make($password);
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->is_admin = true;
        $user->is_active = true;
        $user->role = 'admin';
        $user->save();

        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->info("Local administrator ready: {$email}. Sign in using your chosen password.");

        return self::SUCCESS;
    }
}
