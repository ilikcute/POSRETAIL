<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_brands_api(): void
    {
        $this->getJson('/api/brands')->assertStatus(401);
        $this->postJson('/api/brands', [])->assertStatus(401);
        $this->getJson('/api/brands/1')->assertStatus(401);
        $this->putJson('/api/brands/1', [])->assertStatus(401);
        $this->deleteJson('/api/brands/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_brands_list(): void
    {
        Sanctum::actingAs($this->user);

        Brand::factory()->count(3)->create();

        $response = $this->getJson('/api/brands');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_brand_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);
        Storage::fake('public');

        $logo = UploadedFile::fake()->image('brand_logo.png');

        $payload = [
            'name' => 'Adidas',
            'description' => 'Sportswear manufacturer',
            'is_active' => true,
            'logo' => $logo,
        ];

        $response = $this->postJson('/api/brands', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('brands', [
            'name' => 'Adidas',
            'description' => 'Sportswear manufacturer',
            'is_active' => 1,
        ]);

        $brand = Brand::first();
        $this->assertNotNull($brand->logo_path);
        Storage::disk('public')->assertExists($brand->logo_path);
    }

    public function test_user_cannot_create_brand_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: missing name
        $payload = [
            'description' => 'Invalid brand',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/brands', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid: name too short (our client-side validates min 3, but let's check name is string/max)
        $payloadTooLong = [
            'name' => str_repeat('a', 256),
        ];

        $this->postJson('/api/brands', $payloadTooLong)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_show_brand(): void
    {
        Sanctum::actingAs($this->user);

        $brand = Brand::factory()->create();

        $response = $this->getJson("/api/brands/{$brand->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $brand->name);
    }

    public function test_user_can_update_brand(): void
    {
        Sanctum::actingAs($this->user);
        Storage::fake('public');

        $brand = Brand::factory()->create([
            'name' => 'Puma Old',
            'description' => 'Old description',
            'is_active' => false,
        ]);

        $newLogo = UploadedFile::fake()->image('puma_new_logo.png');

        // Test update using Laravel PUT endpoint
        $payload = [
            'name' => 'Puma New',
            'description' => 'Updated description',
            'is_active' => true,
            'logo' => $newLogo,
        ];

        $response = $this->putJson("/api/brands/{$brand->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Puma New',
            'description' => 'Updated description',
            'is_active' => 1,
        ]);

        $updatedBrand = $brand->fresh();
        $this->assertNotNull($updatedBrand->logo_path);
        Storage::disk('public')->assertExists($updatedBrand->logo_path);
    }

    public function test_user_can_delete_brand(): void
    {
        Sanctum::actingAs($this->user);
        Storage::fake('public');

        $logo = UploadedFile::fake()->image('to_delete.png');
        $brand = Brand::factory()->create([
            'logo_path' => $logo->store('brands/logos', 'public'),
        ]);

        Storage::disk('public')->assertExists($brand->logo_path);

        $response = $this->deleteJson("/api/brands/{$brand->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('brands', ['id' => $brand->id]);
        Storage::disk('public')->assertMissing($brand->logo_path);
    }
}
