<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_suppliers_api(): void
    {
        $this->getJson('/api/suppliers')->assertStatus(401);
        $this->postJson('/api/suppliers', [])->assertStatus(401);
        $this->getJson('/api/suppliers/1')->assertStatus(401);
        $this->putJson('/api/suppliers/1', [])->assertStatus(401);
        $this->deleteJson('/api/suppliers/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_suppliers_list(): void
    {
        Sanctum::actingAs($this->user);

        Supplier::factory()->count(3)->create();

        $response = $this->getJson('/api/suppliers');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_supplier_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'code' => 'SUP-123',
            'name' => 'Supplier Indofood',
            'company' => 'PT Indofood CBP Sukses Makmur',
            'email' => 'contact@indofood.com',
            'phone' => '021-9876543',
            'address' => 'Kawasan Industri Jakarta',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/suppliers', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-123',
            'name' => 'Supplier Indofood',
            'company' => 'PT Indofood CBP Sukses Makmur',
            'email' => 'contact@indofood.com',
            'phone' => '021-9876543',
            'address' => 'Kawasan Industri Jakarta',
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_supplier_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: missing name
        $payload = [
            'code' => 'SUP-001',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/suppliers', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid: name too long
        $payloadTooLong = [
            'name' => str_repeat('a', 256),
        ];

        $this->postJson('/api/suppliers', $payloadTooLong)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_show_supplier(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create();

        $response = $this->getJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $supplier->name);
    }

    public function test_user_can_update_supplier(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'name' => 'Supplier Old',
            'company' => 'Company Old',
            'is_active' => false,
        ]);

        $payload = [
            'name' => 'Supplier New',
            'company' => 'Company New',
            'is_active' => true,
        ];

        $response = $this->putJson("/api/suppliers/{$supplier->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Supplier New',
            'company' => 'Company New',
            'is_active' => 1,
        ]);
    }

    public function test_user_can_delete_supplier(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create();

        $response = $this->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }
}
