<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_units_api(): void
    {
        $this->getJson('/api/units')->assertStatus(401);
        $this->postJson('/api/units', [])->assertStatus(401);
        $this->getJson('/api/units/1')->assertStatus(401);
        $this->putJson('/api/units/1', [])->assertStatus(401);
        $this->deleteJson('/api/units/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_units_list(): void
    {
        Sanctum::actingAs($this->user);

        Unit::factory()->count(3)->create();

        $response = $this->getJson('/api/units');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_unit_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'name' => 'Kilogram',
            'short_name' => 'KG',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/units', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('units', [
            'name' => 'Kilogram',
            'short_name' => 'KG',
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_unit_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: missing name
        $payload = [
            'short_name' => 'PCS',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/units', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid: short_name too long
        $payloadTooLong = [
            'name' => 'Boxes',
            'short_name' => str_repeat('a', 21),
        ];

        $this->postJson('/api/units', $payloadTooLong)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['short_name']);
    }

    public function test_user_can_show_unit(): void
    {
        Sanctum::actingAs($this->user);

        $unit = Unit::factory()->create();

        $response = $this->getJson("/api/units/{$unit->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $unit->name);
    }

    public function test_user_can_update_unit(): void
    {
        Sanctum::actingAs($this->user);

        $unit = Unit::factory()->create([
            'name' => 'Liter Old',
            'short_name' => 'LTR_OLD',
            'is_active' => false,
        ]);

        $payload = [
            'name' => 'Liter New',
            'short_name' => 'LTR',
            'is_active' => true,
        ];

        $response = $this->putJson("/api/units/{$unit->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'Liter New',
            'short_name' => 'LTR',
            'is_active' => 1,
        ]);
    }

    public function test_user_can_delete_unit(): void
    {
        Sanctum::actingAs($this->user);

        $unit = Unit::factory()->create();

        $response = $this->deleteJson("/api/units/{$unit->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }
}
