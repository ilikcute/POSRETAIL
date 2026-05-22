<?php

namespace Database\Seeders;

use App\Enums\PermissionsEnum;
use App\Enums\RolesEnum;
use App\Models\Auth\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Buat semua permissions ────────────────────────────────────────────
        foreach (PermissionsEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        // ── Buat roles ────────────────────────────────────────────────────────
        $superAdmin = Role::findOrCreate(RolesEnum::SuperAdmin->value, 'web');
        $manager = Role::findOrCreate(RolesEnum::Manager->value, 'web');
        $cashier = Role::findOrCreate(RolesEnum::Cashier->value, 'web');

        // ── Assign permissions ke manager ─────────────────────────────────────
        // Manager dapat semua KECUALI user/role management
        // Assign all permissions to manager, including user management
        $managerPermissions = PermissionsEnum::cases();

        $manager->givePermissionTo(PermissionsEnum::ViewUsers->value);

        // ── Assign permissions ke cashier ─────────────────────────────────────
        $cashier->syncPermissions([
            PermissionsEnum::ViewProducts->value,
            PermissionsEnum::ViewCategories->value,
            PermissionsEnum::ViewBrands->value,
            PermissionsEnum::ViewUnits->value,
            PermissionsEnum::ViewCustomers->value,
            PermissionsEnum::CreateCustomers->value,
            PermissionsEnum::EditCustomers->value,
            PermissionsEnum::ViewSales->value,
            PermissionsEnum::CreateSales->value,
            PermissionsEnum::ViewPromotions->value,
            PermissionsEnum::ViewShifts->value,
            PermissionsEnum::ManageShifts->value,
            PermissionsEnum::ViewLoyalty->value,
            PermissionsEnum::ManageLoyalty->value,
            PermissionsEnum::ViewSuspendedCarts->value,
            PermissionsEnum::ManageSuspendedCarts->value,
            PermissionsEnum::ViewCashTransactions->value,
            PermissionsEnum::CreateCashTransactions->value,
        ]);

        // Super Admin tidak perlu explicit permissions (Gate::before handle ini)
        // Tapi tetap beri semua permission agar visible di UI role management
        $superAdmin->syncPermissions(Permission::all());

        // ── Buat user super admin (idempotent) ────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'admin@posretail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );

        $user->syncRoles([RolesEnum::SuperAdmin->value]);
    }
}
