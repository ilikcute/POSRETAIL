<?php

namespace Tests\Feature\Auth;

use App\Enums\RolesEnum;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        $this->superAdmin = User::where('email', 'admin@posretail.com')->first();

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(RolesEnum::Cashier);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_super_admin_can_list_roles(): void
    {
        $this->actingAs($this->superAdmin)
            ->getJson('/api/roles')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_cashier_cannot_list_roles(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/roles')
            ->assertStatus(403);
    }

    // ── Permissions List ──────────────────────────────────────────────────────

    public function test_super_admin_can_get_all_permissions_grouped(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/roles/permissions/all');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Should return grouped structure
        $this->assertIsArray($response->json('data'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_super_admin_can_create_role_with_permissions(): void
    {
        $permName = Permission::first()->name;

        $this->actingAs($this->superAdmin)
            ->postJson('/api/roles', [
                'name' => 'test_role',
                'permissions' => [$permName],
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'test_role');

        $this->assertDatabaseHas('roles', ['name' => 'test_role']);
    }

    public function test_create_role_requires_unique_name(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson('/api/roles', [
                'name' => RolesEnum::Cashier->value,
                'permissions' => [Permission::first()->name],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_role_requires_at_least_one_permission(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson('/api/roles', [
                'name' => 'new_role',
                'permissions' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['permissions']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_super_admin_can_show_role_with_permissions(): void
    {
        $role = Role::where('name', RolesEnum::Cashier->value)->first();

        $this->actingAs($this->superAdmin)
            ->getJson("/api/roles/{$role->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', RolesEnum::Cashier->value);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_super_admin_can_update_role_permissions(): void
    {
        $role = Role::where('name', RolesEnum::Cashier->value)->first();
        $perm = Permission::where('name', 'view sales')->first()->name;

        $this->actingAs($this->superAdmin)
            ->putJson("/api/roles/{$role->id}", [
                'permissions' => [$perm],
            ])
            ->assertStatus(200);

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo($perm));
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_super_admin_cannot_delete_super_admin_role(): void
    {
        $superAdminRole = Role::where('name', RolesEnum::SuperAdmin->value)->first();

        $this->actingAs($this->superAdmin)
            ->deleteJson("/api/roles/{$superAdminRole->id}")
            ->assertStatus(422);
    }

    public function test_super_admin_cannot_delete_role_in_use(): void
    {
        $cashierRole = Role::where('name', RolesEnum::Cashier->value)->first();

        $this->actingAs($this->superAdmin)
            ->deleteJson("/api/roles/{$cashierRole->id}")
            ->assertStatus(422);
    }

    public function test_super_admin_can_delete_unused_role(): void
    {
        $perm = Permission::first()->name;
        $role = Role::create(['name' => 'temp_role', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $this->actingAs($this->superAdmin)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('roles', ['name' => 'temp_role']);
    }

    // ── Sync Permissions ──────────────────────────────────────────────────────

    public function test_super_admin_can_sync_role_permissions(): void
    {
        $role = Role::where('name', RolesEnum::Manager->value)->first();
        $perms = Permission::take(3)->pluck('name')->toArray();

        $this->actingAs($this->superAdmin)
            ->postJson("/api/roles/{$role->id}/sync-permissions", [
                'permissions' => $perms,
            ])
            ->assertStatus(200);

        foreach ($perms as $perm) {
            $this->assertTrue($role->fresh()->hasPermissionTo($perm));
        }
    }
}
