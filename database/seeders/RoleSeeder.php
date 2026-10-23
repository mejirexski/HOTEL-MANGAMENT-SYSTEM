<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'owner', 
            'general-manager', 
            'receptionist', 
            'cashier',
            'bar-manager', 
            'bartender', 
            'storekeeper',
            'gym-manager', 
            'gym-attendant',
            'housekeeping', 
            'maintenance',
            'guest', 
            'customer',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role, 
                'guard_name' => 'web'
                ]);
        }
    }
}
