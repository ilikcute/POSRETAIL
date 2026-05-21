<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_customers_api(): void
    {
        $this->getJson('/api/customers')->assertStatus(401);
        $this->postJson('/api/customers', [])->assertStatus(401);
        $this->getJson('/api/customers/1')->assertStatus(401);
        $this->putJson('/api/customers/1', [])->assertStatus(401);
        $this->deleteJson('/api/customers/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_customers_list(): void
    {
        Sanctum::actingAs($this->user);

        Customer::factory()->count(3)->create();

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_customer_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'name' => 'Alice Margatroid',
            'email' => 'alice@example.com',
            'phone' => '081299991111',
            'address' => 'Forest of Magic',
            'member_code' => '700001',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/customers', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'name' => 'Alice Margatroid',
            'email' => 'alice@example.com',
            'phone' => '081299991111',
            'address' => 'Forest of Magic',
            'member_code' => '700001',
            'point_balance' => 0, // point_balance should not be in the form, and defaults to 0 on database
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_customer_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: missing name
        $payload = [
            'email' => 'invalid@example.com',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/customers', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid: duplicate email
        $existing = Customer::factory()->create(['email' => 'duplicate@example.com']);
        $payloadDuplicate = [
            'name' => 'Bob',
            'email' => 'duplicate@example.com',
        ];

        $this->postJson('/api/customers', $payloadDuplicate)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_show_customer(): void
    {
        Sanctum::actingAs($this->user);

        $customer = Customer::factory()->create();

        $response = $this->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $customer->name);
    }

    public function test_user_can_update_customer(): void
    {
        Sanctum::actingAs($this->user);

        $customer = Customer::factory()->create([
            'name' => 'John Doe Old',
            'email' => 'johndoeold@example.com',
            'is_active' => false,
        ]);

        $payload = [
            'name' => 'John Doe New',
            'email' => 'johndoenew@example.com',
            'phone' => '081288882222',
            'address' => 'Updated Address',
            'member_code' => '700002',
            'is_active' => true,
        ];

        $response = $this->putJson("/api/customers/{$customer->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'John Doe New',
            'email' => 'johndoenew@example.com',
            'phone' => '081288882222',
            'address' => 'Updated Address',
            'member_code' => '700002',
            'is_active' => 1,
        ]);
    }

    public function test_user_can_delete_customer(): void
    {
        Sanctum::actingAs($this->user);

        $customer = Customer::factory()->create();

        $response = $this->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }
}
