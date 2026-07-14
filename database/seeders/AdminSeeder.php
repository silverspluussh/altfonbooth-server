<?php

namespace Database\Seeders;

use App\Models\AdminModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        AdminModel::create([
            'name' => 'Super Admin',
            'username' => 'admin',
            'email' => 'admin@altfon.com',
            'password' => Hash::make('admin123'),
            'role' => 'super_admin',
        ]);

        $this->command->info('Admin account created: username=admin, password=admin123');
    }
}
