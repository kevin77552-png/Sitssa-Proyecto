<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('SEED_SUPERADMIN_EMAIL');
        $password = env('SEED_SUPERADMIN_PASSWORD');

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $password || strlen($password) < 12) {
            throw new RuntimeException('Set SEED_SUPERADMIN_EMAIL and a unique SEED_SUPERADMIN_PASSWORD (12+ characters) before running this seeder.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make($password),
                'role' => 'superadministrador',
                'activo' => true,
            ]
        );
    }
}
