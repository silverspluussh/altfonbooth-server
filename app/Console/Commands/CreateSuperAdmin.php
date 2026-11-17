<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super';
    protected $description = 'Create a super admin user';

    public function handle()
    {
        $name = $this->ask('Full Name');
        $username = $this->ask('Username');
        $email = $this->ask('Email');
        $password = $this->secret('Password');

        if (\App\Models\AdminModel::where('username', $username)->exists()) {
            $this->error('Admin with this username already exists.');
            return;
        }

        \App\Models\AdminModel::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => 'super_admin',
        ]);

        $this->info('Super Admin created successfully!');
    }
}
