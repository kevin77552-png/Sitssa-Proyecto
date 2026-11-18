<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $password || strlen($password) < 12) {
            throw new RuntimeException('Set SEED_ADMIN_EMAIL and a unique SEED_ADMIN_PASSWORD (12+ characters) before running this seeder.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador',
                'password' => Hash::make($password),
                'role' => 'administrador',
                'activo' => true,
            ]
        );
    }
}
