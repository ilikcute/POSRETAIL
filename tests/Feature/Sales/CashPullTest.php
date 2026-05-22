<?php

namespace Tests\Feature\Sales;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Master\Station;
use App\Models\Master\Store;
use App\Models\Sales\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashPullTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Station $station;

    protected Account $drawerAccount;

    protected Account $safeAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create standard models for testing
        $this->user = User::factory()->create();
        $this->store = Store::factory()->create();
        $this->station = Station::factory()->create([
            'drawer_safety_limit' => 1000000.00,
        ]);

        // 2. Create the required Accounts for double-entry journal postings
        $this->drawerAccount = Account::create([
            'code' => '1101',
            'name' => 'Kas Laci POS',
            'type' => 'asset',
            'balance' => 0.00,
            'description' => 'Kas Laci',
        ]);

        $this->safeAccount = Account::create([
            'code' => '1102',
            'name' => 'Brankas Utama',
            'type' => 'asset',
            'balance' => 0.00,
            'description' => 'Brankas Utama',
        ]);
    }

    public function test_guest_cannot_access_cash_pull_api(): void
    {
        $this->getJson('/api/cash-pull/check/'.$this->station->id)->assertStatus(401);
        $this->postJson('/api/cash-pull/execute', [])->assertStatus(401);
    }

    public function test_authenticated_user_can_check_drawer_limit(): void
    {
        Sanctum::actingAs($this->user);

        // Create an open shift
        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 450000.00,
            'expected_cash' => 450000.00,
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/cash-pull/check/'.$this->station->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.station.id', $this->station->id)
            ->assertJsonPath('data.cash_drawer_status.current_cash_in_drawer', 450000)
            ->assertJsonPath('data.cash_drawer_status.is_alert_triggered', false)
            ->assertJsonPath('data.cash_drawer_status.status', 'SAFE (Aman)');

        // Now update the shift to trigger the safety threshold warning
        $shift->update([
            'starting_cash' => 1200000.00,
            'expected_cash' => 1200000.00,
        ]);

        $responseAlert = $this->getJson('/api/cash-pull/check/'.$this->station->id);
        $responseAlert->assertStatus(200)
            ->assertJsonPath('data.cash_drawer_status.is_alert_triggered', true)
            ->assertJsonPath('data.cash_drawer_status.status', 'ALERT_TRIGGERED (Wajib Setor Tengah!)');
    }

    public function test_authenticated_user_fails_check_limit_if_no_active_shift(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/cash-pull/check/'.$this->station->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_active_shift', false)
            ->assertJsonPath('data.status', 'NO_ACTIVE_SHIFT');
    }

    public function test_user_can_execute_cash_pull_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Open shift with Rp 1,500,000 cash in drawer
        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 1500000.00,
            'expected_cash' => 1500000.00,
            'status' => 'open',
        ]);

        $payload = [
            'station_id' => $this->station->id,
            'pull_amount' => 1000000.00,
            'supervisor_id' => $this->user->id,
            'notes' => 'Setor ke brankas utama sore hari',
        ];

        $response = $this->postJson('/api/cash-pull/execute', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.station_name', $this->station->name)
            ->assertJsonPath('data.pulled_amount', 1000000)
            ->assertJsonPath('data.remaining_cash_in_drawer', 500000);

        // Assert database updates
        $this->assertDatabaseHas('cash_transactions', [
            'shift_id' => $shift->id,
            'type' => 'out',
            'amount' => 1000000.00,
            'category' => 'setor_tengah',
        ]);

        // Shift expected cash must be reduced
        $this->assertEquals(500000.00, $shift->fresh()->expected_cash);
    }

    public function test_user_cannot_execute_cash_pull_exceeding_drawer_cash(): void
    {
        Sanctum::actingAs($this->user);

        Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 300000.00,
            'expected_cash' => 300000.00,
            'status' => 'open',
        ]);

        $payload = [
            'station_id' => $this->station->id,
            'pull_amount' => 500000.00, // Rp 500.000 (Exceeds Rp 300.000 actual cash)
            'supervisor_id' => $this->user->id,
            'notes' => 'Invalid pull amount',
        ];

        $this->postJson('/api/cash-pull/execute', $payload)
            ->assertStatus(422);
    }

    public function test_redirection_and_session_flashes_for_traditional_web_requests(): void
    {
        Sanctum::actingAs($this->user);

        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 1200000.00,
            'expected_cash' => 1200000.00,
            'status' => 'open',
        ]);

        $payload = [
            'station_id' => $this->station->id,
            'pull_amount' => 700000.00,
            'supervisor_id' => $this->user->id,
            'notes' => 'Traditional setoran',
        ];

        // Call without JSON headers to simulate traditional web form submission
        $response = $this->from('/cash-pull-tab')
            ->post('/api/cash-pull/execute', $payload);

        $response->assertRedirect('/cash-pull-tab');
        $response->assertSessionHas('success', 'Setor tengah berhasil dieksekusi dan dibukukan!');

        $this->assertEquals(500000.00, $shift->fresh()->expected_cash);
    }
}
