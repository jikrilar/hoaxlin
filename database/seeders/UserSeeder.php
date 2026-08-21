<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrator Hoaxlin', 'email' => 'admin@hoaxlin.id', 'is_admin' => true],
            ['name' => 'Budi Santoso', 'email' => 'budi@hoaxlin.id', 'is_admin' => false],
            ['name' => 'Siti Rahmawati', 'email' => 'siti@hoaxlin.id', 'is_admin' => false],
            ['name' => 'Andi Pratama', 'email' => 'andi@hoaxlin.id', 'is_admin' => false],
            ['name' => 'Dewi Lestari', 'email' => 'dewi@hoaxlin.id', 'is_admin' => false],
            ['name' => 'Rizky Maulana', 'email' => 'rizky@hoaxlin.id', 'is_admin' => false],
        ];

        foreach ($users as $attributes) {
            User::updateOrCreate(
                ['email' => $attributes['email']],
                $attributes + [
                    'email_verified_at' => now(),
                    'password' => 'password',
                ],
            );
        }
    }
}
