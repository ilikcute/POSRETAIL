<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_warehouses_api(): void
    {
        $this->getJson('/api/warehouses')->assertStatus(401);
        $this->postJson('/api/warehouses', [])->assertStatus(401);
        $this->getJson('/api/warehouses/1')->assertStatus(401);
        $this->putJson('/api/warehouses/1', [])->assertStatus(401);
        $this->deleteJson('/api/warehouses/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_warehouses_list(): void
    {
        Sanctum::actingAs($this->user);

        Warehouse::factory()->count(3)->create();

        $response = $this->getJson('/api/warehouses');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_warehouse_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'code' => 'WH-TEST-001',
            'name' => 'Main Warehouse Test',
            'address' => '123 Test Street, City',
            'is_main' => true,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/warehouses', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('warehouses', [
            'code' => 'WH-TEST-001',
            'name' => 'Main Warehouse Test',
            'address' => '123 Test Street, City',
            'is_main' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_warehouse_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: missing code and name
        $payload = [
            'address' => 'No code or name',
            'is_main' => true,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/warehouses', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);

        // Invalid: code too long (max 50)
        $payloadTooLongCode = [
            'code' => str_repeat('A', 51),
            'name' => 'Valid Name',
        ];

        $this->postJson('/api/warehouses', $payloadTooLongCode)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // Invalid: name too long (max 255)
        $payloadTooLongName = [
            'code' => 'WH-01',
            'name' => str_repeat('B', 256),
        ];

        $this->postJson('/api/warehouses', $payloadTooLongName)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_cannot_create_warehouse_with_duplicate_code(): void
    {
        Sanctum::actingAs($this->user);

        Warehouse::factory()->create([
            'code' => 'WH-DUPLICATE',
        ]);

        $payload = [
            'code' => 'WH-DUPLICATE',
            'name' => 'Another Warehouse',
        ];

        $response = $this->postJson('/api/warehouses', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_user_can_show_warehouse(): void
    {
        Sanctum::actingAs($this->user);

        $warehouse = Warehouse::factory()->create();

        $response = $this->getJson("/api/warehouses/{$warehouse->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $warehouse->name);
    }

    public function test_user_can_update_warehouse(): void
    {
        Sanctum::actingAs($this->user);

        $warehouse = Warehouse::factory()->create([
            'code' => 'WH-OLD',
            'name' => 'Warehouse Old Name',
            'address' => 'Old Address',
            'is_main' => false,
            'is_active' => false,
        ]);

        $payload = [
            'code' => 'WH-NEW',
            'name' => 'Warehouse New Name',
            'address' => 'New Address',
            'is_main' => true,
            'is_active' => true,
        ];

        $response = $this->putJson("/api/warehouses/{$warehouse->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'code' => 'WH-NEW',
            'name' => 'Warehouse New Name',
            'address' => 'New Address',
            'is_main' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_user_can_delete_warehouse(): void
    {
        Sanctum::actingAs($this->user);

        $warehouse = Warehouse::factory()->create();

        $response = $this->deleteJson("/api/warehouses/{$warehouse->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    }
}
