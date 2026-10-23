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
      $this->call(RoleSeeder::class);

        $owner = User::firstOrCreate(
            ['email' => 'owner@hotel.test'],
            ['name' => 'Hotel Owner', 'password' => bcrypt('change-me-now'), 'is_active' => true]
        );
        $owner->assignRole('owner');
    }
}
