<?php

namespace Database\Seeders;

use App\Models\Auth\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Buat roles
        $superAdmin = Role::create(['name' => 'super_admin']);
        $manager = Role::create(['name' => 'manager']);
        $cashier = Role::create(['name' => 'cashier']);

        // Buat user super admin pertama
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@posretail.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Assign role
        $user->assignRole($superAdmin);
    }
}
