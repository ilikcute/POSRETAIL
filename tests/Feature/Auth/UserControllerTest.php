<?php

namespace Tests\Feature\Auth;

use App\Enums\RolesEnum;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions and roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        $this->superAdmin = User::where('email', 'admin@posretail.com')->first();

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(RolesEnum::Cashier);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_super_admin_can_list_users(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['data']]);
    }

    public function test_cashier_cannot_list_users(): void
    {
        $response = $this->actingAs($this->cashier)
            ->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_list_users(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_super_admin_can_create_user(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@posretail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => RolesEnum::Cashier->value,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.email', 'test@posretail.com');

        $this->assertDatabaseHas('users', ['email' => 'test@posretail.com']);
    }

    public function test_create_user_validates_duplicate_email(): void
    {
        $payload = [
            'name' => 'Duplicate',
            'email' => $this->cashier->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => RolesEnum::Cashier->value,
        ];

        $this->actingAs($this->superAdmin)
            ->postJson('/api/users', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_user_requires_valid_role(): void
    {
        $payload = [
            'name' => 'Test',
            'email' => 'new@posretail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'nonexistent_role',
        ];

        $this->actingAs($this->superAdmin)
            ->postJson('/api/users', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_super_admin_can_show_user(): void
    {
        $this->actingAs($this->superAdmin)
            ->getJson("/api/users/{$this->cashier->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.email', $this->cashier->email);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_super_admin_can_update_user(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson("/api/users/{$this->cashier->id}", ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // ── Toggle Active ─────────────────────────────────────────────────────────

    public function test_super_admin_can_toggle_user_active_status(): void
    {
        $initialStatus = $this->cashier->is_active;

        $response = $this->actingAs($this->superAdmin)
            ->patchJson("/api/users/{$this->cashier->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', ! $initialStatus);
    }

    public function test_user_cannot_toggle_own_active_status(): void
    {
        $this->actingAs($this->superAdmin)
            ->patchJson("/api/users/{$this->superAdmin->id}/toggle-active")
            ->assertStatus(422);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_super_admin_can_delete_cashier(): void
    {
        $this->actingAs($this->superAdmin)
            ->deleteJson("/api/users/{$this->cashier->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('users', ['id' => $this->cashier->id]);
    }

    public function test_super_admin_cannot_delete_self(): void
    {
        $this->actingAs($this->superAdmin)
            ->deleteJson("/api/users/{$this->superAdmin->id}")
            ->assertStatus(422);
    }

    public function test_cannot_delete_last_super_admin(): void
    {
        // super_admin is the only one with that role
        $this->actingAs($this->superAdmin)
            ->deleteJson("/api/users/{$this->superAdmin->id}")
            ->assertStatus(422);
    }

    // ── Sync Roles ────────────────────────────────────────────────────────────

    public function test_super_admin_can_sync_user_roles(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson("/api/users/{$this->cashier->id}/sync-roles", [
                'roles' => [RolesEnum::Manager->value],
            ])
            ->assertStatus(200);

        $this->assertTrue($this->cashier->fresh()->hasRole(RolesEnum::Manager));
    }
}
