<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email} {name}';

    protected $description = 'Create an administrator. You are asked for the password; it is never stored in a file.';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $password = (string) $this->secret('Password (at least 10 characters, letters and numbers)');
        $v = Validator::make(['email' => $email, 'password' => $password], ['email' => 'required|email|unique:users,email', 'password' => ['required', Password::defaults()]]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }
        User::create(['name' => trim($this->argument('name')), 'email' => $email, 'password' => $password, 'role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
