<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_categories_api(): void
    {
        $this->getJson('/api/categories')->assertStatus(401);
        $this->postJson('/api/categories', [])->assertStatus(401);
        $this->getJson('/api/categories/1')->assertStatus(401);
        $this->putJson('/api/categories/1', [])->assertStatus(401);
        $this->deleteJson('/api/categories/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_categories_list(): void
    {
        Sanctum::actingAs($this->user);

        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_category_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'name' => 'Beverages',
            'description' => 'Soft drinks, juices, coffee, and tea',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/categories', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('categories', [
            'name' => 'Beverages',
            'description' => 'Soft drinks, juices, coffee, and tea',
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_category_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: missing name
        $payload = [
            'description' => 'Invalid category',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/categories', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid: name too long
        $payloadTooLong = [
            'name' => str_repeat('a', 256),
        ];

        $this->postJson('/api/categories', $payloadTooLong)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_show_category(): void
    {
        Sanctum::actingAs($this->user);

        $category = Category::factory()->create();

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $category->name);
    }

    public function test_user_can_update_category(): void
    {
        Sanctum::actingAs($this->user);

        $category = Category::factory()->create([
            'name' => 'Beverages Old',
            'description' => 'Old description',
            'is_active' => false,
        ]);

        $payload = [
            'name' => 'Beverages New',
            'description' => 'Updated description',
            'is_active' => true,
        ];

        $response = $this->putJson("/api/categories/{$category->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Beverages New',
            'description' => 'Updated description',
            'is_active' => 1,
        ]);
    }

    public function test_user_can_delete_category(): void
    {
        Sanctum::actingAs($this->user);

        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }
}
