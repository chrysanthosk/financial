<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // If an admin already exists, do nothing
        if (User::where('role', 'admin')->exists()) {
            return;
        }

        User::create([
            'name' => 'Admin',
            'email' => 'admin@financial.i-portal.me',
            'password' => Hash::make('ChangeMe123!'),
            'role' => 'admin',
            'theme' => 'dark',
        ]);
    }
}
