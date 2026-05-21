<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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
