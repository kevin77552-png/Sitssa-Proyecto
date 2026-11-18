<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use App\Models\User;

class ManagerUserSeeder extends Seeder
{
    public function run()
    {
        $email = env('SEED_MANAGER_EMAIL');
        $password = env('SEED_MANAGER_PASSWORD');

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $password || strlen($password) < 12) {
            throw new RuntimeException('Set SEED_MANAGER_EMAIL and a unique SEED_MANAGER_PASSWORD (12+ characters) before running this seeder.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Gerente',
                'password' => Hash::make($password),
                'role' => 'gerente',
                'activo' => true,
            ]
        );
    }
}
