<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_stores_api(): void
    {
        $this->getJson('/api/stores')->assertStatus(401);
        $this->postJson('/api/stores', [])->assertStatus(401);
        $this->getJson('/api/stores/1')->assertStatus(401);
        $this->putJson('/api/stores/1', [])->assertStatus(401);
        $this->deleteJson('/api/stores/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_stores_list(): void
    {
        Sanctum::actingAs($this->user);

        Store::factory()->count(3)->create();

        $response = $this->getJson('/api/stores');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_store_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'name' => 'Mega Store Retail',
            'address' => '456 Business Road, Capital City',
            'phone' => '021-1234567',
            'email' => 'mega.store@retail.com',
            'tax_number' => '12.345.678.9-012.000',
            'header_text' => 'Welcome to Mega Store',
            'footer_text' => 'Thank you!',
            'default_printer_id' => 1,
            'default_receipt_template_id' => 2,
            'is_active' => true,
            'print_settings' => [
                'paper_width' => '80mm',
                'show_logo' => true,
            ],
        ];

        $response = $this->postJson('/api/stores', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('stores', [
            'name' => 'Mega Store Retail',
            'address' => '456 Business Road, Capital City',
            'phone' => '021-1234567',
            'email' => 'mega.store@retail.com',
            'tax_number' => '12.345.678.9-012.000',
            'header_text' => 'Welcome to Mega Store',
            'footer_text' => 'Thank you!',
            'default_printer_id' => 1,
            'default_receipt_template_id' => 2,
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_store_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: missing required name
        $payload = [
            'address' => 'No name here',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/stores', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid: name too long
        $payloadTooLongName = [
            'name' => str_repeat('A', 256),
        ];

        $this->postJson('/api/stores', $payloadTooLongName)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid: bad email format
        $payloadBadEmail = [
            'name' => 'Test Store',
            'email' => 'invalid-email-format',
        ];

        $this->postJson('/api/stores', $payloadBadEmail)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_show_store(): void
    {
        Sanctum::actingAs($this->user);

        $store = Store::factory()->create();

        $response = $this->getJson("/api/stores/{$store->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $store->name);
    }

    public function test_user_can_update_store(): void
    {
        Sanctum::actingAs($this->user);

        $store = Store::factory()->create([
            'name' => 'Old Store Name',
            'is_active' => false,
        ]);

        $payload = [
            'name' => 'New Store Name',
            'address' => 'Updated Address',
            'is_active' => true,
        ];

        $response = $this->putJson("/api/stores/{$store->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'name' => 'New Store Name',
            'address' => 'Updated Address',
            'is_active' => 1,
        ]);
    }

    public function test_user_can_delete_store(): void
    {
        Sanctum::actingAs($this->user);

        $store = Store::factory()->create();

        $response = $this->deleteJson("/api/stores/{$store->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('stores', ['id' => $store->id]);
    }
}
