<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@levita.com'],
            [
                'name' => 'Admin Levita',
                'password' => Hash::make('Levita26*'),
                'role' => User::ROLE_OWNER,
                'is_admin' => true,
                'account_id' => null,
                'email_verified_at' => now(),
            ]
        );
    }
}
