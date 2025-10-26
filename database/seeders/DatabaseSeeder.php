<?php

namespace Database\Seeders;

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
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'jehad',
            'email' => 'jeha710@gmail.com',
            'phone' => '0910406699',
            'password' => bcrypt('jehad123'),
            'status' => 'active',
        ]);
    }
}
