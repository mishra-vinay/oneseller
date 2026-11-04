<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Admin accounts have no public registration; they are created on the server with this command.
 */
#[Signature('oneseller:create-admin {--name=} {--email=}')]
#[Description('Create an admin account')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: $this->ask('Name'),
            'email' => $this->option('email') ?: $this->ask('Email'),
            'password' => $this->secret('Password (min 8 characters, letters and numbers)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User($data);
        $user->role = Role::Admin;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Admin {$user->email} created. Log in at ".route('admin.login'));

        return self::SUCCESS;
    }
}
