<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Product;
use App\Models\Master\Rack;
use App\Models\Master\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RackTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();

        // Create a warehouse for relations
        $this->warehouse = Warehouse::factory()->create();
    }

    public function test_guest_cannot_access_racks_api(): void
    {
        $this->getJson('/api/racks')->assertStatus(401);
        $this->postJson('/api/racks', [])->assertStatus(401);
        $this->getJson('/api/racks/1')->assertStatus(401);
        $this->putJson('/api/racks/1', [])->assertStatus(401);
        $this->deleteJson('/api/racks/1')->assertStatus(401);
        $this->getJson('/api/racks/1/planogram')->assertStatus(401);
        $this->postJson('/api/racks/1/planogram', [])->assertStatus(401);
    }

    public function test_authenticated_user_can_get_racks_list(): void
    {
        Sanctum::actingAs($this->user);

        Rack::factory()->count(3)->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->getJson('/api/racks');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_rack_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RCK-TEST-99',
            'name' => 'Freezer Rack 99',
            'description' => 'Aisles 4, Upper Freezer Section',
            'sort_order' => 5,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/racks', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('racks', [
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RCK-TEST-99',
            'name' => 'Freezer Rack 99',
            'description' => 'Aisles 4, Upper Freezer Section',
            'sort_order' => 5,
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_rack_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: missing warehouse, code, and name
        $payload = [
            'description' => 'Missing fields',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/racks', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_id', 'code', 'name']);

        // Invalid: non-existing warehouse ID
        $payloadBadWarehouse = [
            'warehouse_id' => 9999,
            'code' => 'RCK-01',
            'name' => 'Invalid Warehouse Rack',
        ];

        $this->postJson('/api/racks', $payloadBadWarehouse)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_id']);

        // Invalid: code too long (max 50)
        $payloadTooLongCode = [
            'warehouse_id' => $this->warehouse->id,
            'code' => str_repeat('A', 51),
            'name' => 'Valid Name',
        ];

        $this->postJson('/api/racks', $payloadTooLongCode)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_user_cannot_create_rack_with_duplicate_code(): void
    {
        Sanctum::actingAs($this->user);

        Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RCK-DUPLICATE',
        ]);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RCK-DUPLICATE',
            'name' => 'Another Rack',
        ];

        $response = $this->postJson('/api/racks', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_user_can_show_rack(): void
    {
        Sanctum::actingAs($this->user);

        $rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->getJson("/api/racks/{$rack->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $rack->name);
    }

    public function test_user_can_update_rack(): void
    {
        Sanctum::actingAs($this->user);

        $rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RCK-OLD',
            'name' => 'Rack Old Name',
            'description' => 'Old Description',
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RCK-NEW',
            'name' => 'Rack New Name',
            'description' => 'New Description',
            'sort_order' => 2,
            'is_active' => true,
        ];

        $response = $this->putJson("/api/racks/{$rack->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('racks', [
            'id' => $rack->id,
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RCK-NEW',
            'name' => 'Rack New Name',
            'description' => 'New Description',
            'sort_order' => 2,
            'is_active' => 1,
        ]);
    }

    public function test_user_can_delete_rack(): void
    {
        Sanctum::actingAs($this->user);

        $rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->deleteJson("/api/racks/{$rack->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('racks', ['id' => $rack->id]);
    }

    public function test_user_can_retrieve_planogram_layout(): void
    {
        Sanctum::actingAs($this->user);

        $rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $product = Product::factory()->create();

        $rack->products()->attach($product->id, [
            'shelf_level' => 2,
            'position_order' => 1,
            'facing' => 2,
            'max_capacity' => 15,
        ]);

        $response = $this->getJson("/api/racks/{$rack->id}/planogram");

        $response->assertStatus(200)
            ->assertJsonPath('data.rack.code', $rack->code)
            ->assertJsonPath('data.shelves.0.shelf_level', 2)
            ->assertJsonPath('data.shelves.0.products.0.id', $product->id)
            ->assertJsonPath('data.shelves.0.products.0.planogram.facing', 2);
    }

    public function test_user_can_update_planogram_layout(): void
    {
        Sanctum::actingAs($this->user);

        $rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $payload = [
            'items' => [
                [
                    'product_id' => $product1->id,
                    'shelf_level' => 1,
                    'position_order' => 1,
                    'facing' => 3,
                    'max_capacity' => 20,
                ],
                [
                    'product_id' => $product2->id,
                    'shelf_level' => 3,
                    'position_order' => 2,
                    'facing' => 1,
                    'max_capacity' => 5,
                ],
            ],
        ];

        $response = $this->postJson("/api/racks/{$rack->id}/planogram", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('product_rack', [
            'rack_id' => $rack->id,
            'product_id' => $product1->id,
            'shelf_level' => 1,
            'position_order' => 1,
            'facing' => 3,
            'max_capacity' => 20,
        ]);

        $this->assertDatabaseHas('product_rack', [
            'rack_id' => $rack->id,
            'product_id' => $product2->id,
            'shelf_level' => 3,
            'position_order' => 2,
            'facing' => 1,
            'max_capacity' => 5,
        ]);
    }
}
