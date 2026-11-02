<?php

namespace Database\Seeders;

use App\Modules\Users\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Development accounts (password for both: "password"):
     *   admin@shop.test     - administrator
     *   customer@shop.test  - customer
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@shop.test',
        ]);

        User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@shop.test',
        ]);
    }
}
