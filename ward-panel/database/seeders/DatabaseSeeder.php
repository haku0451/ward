<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Ward Admin',
            'email' => 'admin@ward.test',
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'name' => 'Ward Viewer',
            'email' => 'viewer@ward.test',
        ]);
    }
}
