<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_stations_api(): void
    {
        $this->getJson('/api/stations')->assertStatus(401);
        $this->postJson('/api/stations', [])->assertStatus(401);
        $this->getJson('/api/stations/1')->assertStatus(401);
        $this->putJson('/api/stations/1', [])->assertStatus(401);
        $this->deleteJson('/api/stations/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_stations_list(): void
    {
        Sanctum::actingAs($this->user);

        Station::factory()->count(3)->create();

        $response = $this->getJson('/api/stations');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_station_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'name' => 'Cashier Station 1',
            'ip_address' => '192.168.1.100',
            'location' => 'Front Counter A',
            'drawer_safety_limit' => 2500000.00,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/stations', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('stations', [
            'name' => 'Cashier Station 1',
            'ip_address' => '192.168.1.100',
            'location' => 'Front Counter A',
            'drawer_safety_limit' => 2500000.00,
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_station_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: missing name
        $payload = [
            'ip_address' => '192.168.1.100',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/stations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid: ip_address format
        $payloadInvalidIp = [
            'name' => 'Cashier 2',
            'ip_address' => 'not-an-ip',
        ];

        $this->postJson('/api/stations', $payloadInvalidIp)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ip_address']);

        // Invalid: drawer_safety_limit not numeric
        $payloadInvalidLimit = [
            'name' => 'Cashier 3',
            'drawer_safety_limit' => 'invalid-limit',
        ];

        $this->postJson('/api/stations', $payloadInvalidLimit)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['drawer_safety_limit']);
    }

    public function test_user_can_show_station(): void
    {
        Sanctum::actingAs($this->user);

        $station = Station::factory()->create();

        $response = $this->getJson("/api/stations/{$station->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $station->name);
    }

    public function test_user_can_update_station(): void
    {
        Sanctum::actingAs($this->user);

        $station = Station::factory()->create([
            'name' => 'Station Old',
            'ip_address' => '192.168.1.10',
            'drawer_safety_limit' => 1000000.00,
            'is_active' => false,
        ]);

        $payload = [
            'name' => 'Station New',
            'ip_address' => '192.168.1.20',
            'drawer_safety_limit' => 1500000.00,
            'is_active' => true,
        ];

        $response = $this->putJson("/api/stations/{$station->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('stations', [
            'id' => $station->id,
            'name' => 'Station New',
            'ip_address' => '192.168.1.20',
            'drawer_safety_limit' => 1500000.00,
            'is_active' => 1,
        ]);
    }

    public function test_user_can_delete_station(): void
    {
        Sanctum::actingAs($this->user);

        $station = Station::factory()->create();

        $response = $this->deleteJson("/api/stations/{$station->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('stations', ['id' => $station->id]);
    }
}
