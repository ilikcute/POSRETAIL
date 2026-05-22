<?php

namespace Tests\Feature\Sales;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Finance\CashTransaction;
use App\Models\Master\Station;
use App\Models\Master\Store;
use App\Models\Master\Warehouse;
use App\Models\Sales\Sale;
use App\Models\Sales\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StationRemittanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Station $station;

    protected Store $store;

    protected Warehouse $warehouse;

    protected Account $drawerAccount;

    protected Account $safeAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->station = Station::factory()->create();
        $this->store = Store::factory()->create();
        $this->warehouse = Warehouse::factory()->create();

        // Required GL accounts for double-entry posting
        $this->drawerAccount = Account::create([
            'code' => '1101',
            'name' => 'Kas Laci POS',
            'type' => 'asset',
            'balance' => 0.00,
        ]);

        $this->safeAccount = Account::create([
            'code' => '1102',
            'name' => 'Bank POS / Brankas',
            'type' => 'asset',
            'balance' => 0.00,
        ]);

        Account::create([
            'code' => '4101',
            'name' => 'Pendapatan Penjualan',
            'type' => 'revenue',
            'balance' => 0.00,
        ]);

        Account::create([
            'code' => '1201',
            'name' => 'Persediaan Barang Dagang',
            'type' => 'asset',
            'balance' => 1000000.00,
        ]);

        Account::create([
            'code' => '5101',
            'name' => 'Harga Pokok Penjualan',
            'type' => 'expense',
            'balance' => 0.00,
        ]);
    }

    // =========================================================================
    // HELPER: Create a standard open shift
    // =========================================================================
    private function createOpenShift(float $startingCash = 100000.00): Shift
    {
        return Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => $startingCash,
            'expected_cash' => $startingCash,
            'status' => 'open',
        ]);
    }

    // =========================================================================
    // 1. GUEST ACCESS RESTRICTION
    // =========================================================================

    public function test_guest_cannot_access_remittance_summary(): void
    {
        $this->getJson('/api/remittance/summary/1')->assertStatus(401);
    }

    public function test_guest_cannot_submit_remittance(): void
    {
        $this->postJson('/api/remittance/submit', [])->assertStatus(401);
    }

    // =========================================================================
    // 2. GET SUMMARY — OPEN SHIFT
    // =========================================================================

    public function test_get_summary_returns_live_balances_for_open_shift(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift(200000.00);

        $response = $this->getJson("/api/remittance/summary/{$shift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.shift.status', 'OPEN')
            ->assertJsonPath('data.shift.id', $shift->id)
            ->assertJsonStructure([
                'data' => [
                    'shift' => ['id', 'cashier_name', 'station_name', 'status', 'start_time'],
                    'live_balances' => [
                        'starting_cash', 'cash_sales', 'qris_sales', 'card_sales',
                        'cash_in', 'cash_out', 'expected_cash', 'expected_qris',
                        'expected_card', 'total_sales', 'total_discount', 'total_transactions',
                    ],
                ],
            ]);

        // No sales yet → expected cash = starting cash
        $this->assertEquals(200000.00, $response->json('data.live_balances.expected_cash'));
        $this->assertEquals(0, $response->json('data.live_balances.total_transactions'));
    }

    public function test_get_summary_includes_cash_sales_in_expected_cash(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift(100000.00);

        // Create a cash sale attached to this shift
        Sale::create([
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'warehouse_id' => $this->warehouse->id,
            'shift_id' => $shift->id,
            'created_by' => $this->user->id,
            'invoice_no' => 'INV-TEST-001',
            'status' => 'completed',
            'payment_method' => 'cash',
            'total_items' => 1,
            'total_amount' => 50000,
            'grand_total' => 50000,
            'amount_paid' => 50000,
            'payment_status' => 'paid',
        ]);

        $response = $this->getJson("/api/remittance/summary/{$shift->id}");

        $response->assertStatus(200);
        // expected_cash = 100000 (starting) + 50000 (cash sale) = 150000
        $this->assertEquals(150000.00, (float) $response->json('data.live_balances.expected_cash'));
        $this->assertEquals(50000.00, (float) $response->json('data.live_balances.cash_sales'));
        $this->assertEquals(1, $response->json('data.live_balances.total_transactions'));
    }

    public function test_get_summary_accounts_for_petty_cash_movements(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift(100000.00);

        // Add petty cash IN
        CashTransaction::create([
            'store_id' => $this->store->id,
            'shift_id' => $shift->id,
            'type' => 'in',
            'category' => 'petty_cash',
            'payment_method' => 'cash',
            'amount' => 20000,
            'description' => 'Penerimaan titipan vendor',
            'created_by' => $this->user->id,
        ]);

        // Add petty cash OUT (setor tengah)
        CashTransaction::create([
            'store_id' => $this->store->id,
            'shift_id' => $shift->id,
            'type' => 'out',
            'category' => 'setor_tengah',
            'payment_method' => 'cash',
            'amount' => 30000,
            'description' => 'Setor Tengah ke Brankas',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/remittance/summary/{$shift->id}");

        $response->assertStatus(200);
        // expected_cash = 100000 + 20000 - 30000 = 90000
        $this->assertEquals(90000.00, (float) $response->json('data.live_balances.expected_cash'));
        $this->assertEquals(20000.00, (float) $response->json('data.live_balances.cash_in'));
        $this->assertEquals(30000.00, (float) $response->json('data.live_balances.cash_out'));
    }

    public function test_get_summary_returns_final_reconciliation_for_closed_shift(): void
    {
        Sanctum::actingAs($this->user);

        // Create an already-closed shift
        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now()->subHours(8),
            'end_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 150000.00,
            'actual_cash' => 145000.00,
            'difference_cash' => -5000.00,
            'expected_qris' => 0.00,
            'actual_qris' => 0.00,
            'difference_qris' => 0.00,
            'expected_card' => 0.00,
            'actual_card' => 0.00,
            'difference_card' => 0.00,
            'total_sales' => 50000.00,
            'total_discount' => 0.00,
            'status' => 'closed',
        ]);

        $response = $this->getJson("/api/remittance/summary/{$shift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.shift.status', 'CLOSED')
            ->assertJsonStructure([
                'data' => [
                    'shift' => ['id', 'cashier_name', 'station_name', 'status', 'start_time', 'end_time'],
                    'final_reconciliation' => [
                        'expected_cash', 'actual_cash', 'difference_cash',
                        'expected_qris', 'actual_qris', 'difference_qris',
                        'expected_card', 'actual_card', 'difference_card',
                    ],
                ],
            ]);

        $this->assertEquals(145000.00, (float) $response->json('data.final_reconciliation.actual_cash'));
        $this->assertEquals(-5000.00, (float) $response->json('data.final_reconciliation.difference_cash'));
    }

    public function test_get_summary_returns_404_for_nonexistent_shift(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/remittance/summary/99999')->assertStatus(404);
    }

    // =========================================================================
    // 3. SUBMIT REMITTANCE
    // =========================================================================

    public function test_submit_remittance_requires_shift_id(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/remittance/submit', [
            'actual_cash' => 100000,
            'actual_qris' => 0,
            'actual_card' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['shift_id']);
    }

    public function test_submit_remittance_requires_actual_cash_and_qris_and_card(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift();

        $this->postJson('/api/remittance/submit', [
            'shift_id' => $shift->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['actual_cash', 'actual_qris', 'actual_card']);
    }

    public function test_submit_remittance_fails_if_shift_already_closed(): void
    {
        Sanctum::actingAs($this->user);

        $closedShift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now()->subHours(8),
            'end_time' => now()->subHours(1),
            'starting_cash' => 100000,
            'expected_cash' => 100000,
            'actual_cash' => 100000,
            'difference_cash' => 0,
            'expected_qris' => 0,
            'actual_qris' => 0,
            'difference_qris' => 0,
            'expected_card' => 0,
            'actual_card' => 0,
            'difference_card' => 0,
            'total_sales' => 0,
            'total_discount' => 0,
            'status' => 'closed',
        ]);

        $response = $this->postJson('/api/remittance/submit', [
            'shift_id' => $closedShift->id,
            'actual_cash' => 100000,
            'actual_qris' => 0,
            'actual_card' => 0,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('ditutup', strtolower($response->json('message')));
    }

    public function test_submit_remittance_succeeds_with_balanced_cash(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift(100000.00);

        // Create cash sale of 50000 → expected_cash = 150000
        Sale::create([
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'warehouse_id' => $this->warehouse->id,
            'shift_id' => $shift->id,
            'created_by' => $this->user->id,
            'invoice_no' => 'INV-REMIT-001',
            'status' => 'completed',
            'payment_method' => 'cash',
            'total_items' => 1,
            'total_amount' => 50000,
            'grand_total' => 50000,
            'amount_paid' => 50000,
            'payment_status' => 'paid',
        ]);

        $response = $this->postJson('/api/remittance/submit', [
            'shift_id' => $shift->id,
            'actual_cash' => 150000, // Exactly as expected → BALANCE
            'actual_qris' => 0,
            'actual_card' => 0,
            'notes' => 'Test rekonsiliasi balance',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Rekonsiliasi dan setoran kasir berhasil diproses.')
            ->assertJsonStructure([
                'data' => [
                    'shift_id',
                    'cashier_name',
                    'station_name',
                    'status',
                    'reconciliation' => [
                        'expected_cash', 'actual_cash', 'difference_cash',
                        'cash_status', 'cash_status_label',
                    ],
                    'journal_reference',
                ],
            ]);

        // Status must be BALANCE
        $this->assertEquals('BALANCE', $response->json('data.reconciliation.cash_status'));
        $this->assertEquals(0.0, (float) $response->json('data.reconciliation.difference_cash'));

        // Shift must now be closed
        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'status' => 'closed',
        ]);

        // Journal entry must exist
        $this->assertDatabaseHas('journal_entries', [
            'created_by' => $this->user->id,
        ]);
    }

    public function test_submit_remittance_detects_cash_shortage_and_posts_journal(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift(100000.00);

        // Expected cash = 100000 (no sales)
        // Actual cash = 90000 → shortage of -10000
        $response = $this->postJson('/api/remittance/submit', [
            'shift_id' => $shift->id,
            'actual_cash' => 90000,
            'actual_qris' => 0,
            'actual_card' => 0,
            'notes' => 'Test shortage',
        ]);

        $response->assertStatus(200);

        $this->assertEquals('SHORTAGE', $response->json('data.reconciliation.cash_status'));
        $this->assertEquals(-10000.0, (float) $response->json('data.reconciliation.difference_cash'));

        // Shortage account (5999) must have been debited
        $shortageAccount = Account::where('code', '5999')->first();
        $this->assertNotNull($shortageAccount, 'Shortage account 5999 must be auto-created.');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'status' => 'closed']);
    }

    public function test_submit_remittance_detects_cash_overage_and_posts_journal(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift(100000.00);

        // Expected cash = 100000 (no sales)
        // Actual cash = 110000 → overage of +10000
        $response = $this->postJson('/api/remittance/submit', [
            'shift_id' => $shift->id,
            'actual_cash' => 110000,
            'actual_qris' => 0,
            'actual_card' => 0,
            'notes' => 'Test overage',
        ]);

        $response->assertStatus(200);

        $this->assertEquals('OVERAGE', $response->json('data.reconciliation.cash_status'));
        $this->assertEquals(10000.0, (float) $response->json('data.reconciliation.difference_cash'));

        // Overage account (6199) must have been auto-created and credited
        $overageAccount = Account::where('code', '6199')->first();
        $this->assertNotNull($overageAccount, 'Overage account 6199 must be auto-created.');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'status' => 'closed']);
    }

    public function test_submit_remittance_cannot_be_done_twice_on_same_shift(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift(100000.00);

        $payload = [
            'shift_id' => $shift->id,
            'actual_cash' => 100000,
            'actual_qris' => 0,
            'actual_card' => 0,
        ];

        // First submission succeeds
        $this->postJson('/api/remittance/submit', $payload)->assertStatus(200);

        // Second submission fails with 400 (already closed)
        $this->postJson('/api/remittance/submit', $payload)->assertStatus(400);
    }

    public function test_submit_remittance_includes_qris_and_card_reconciliation(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift(100000.00);

        // Add QRIS sale
        Sale::create([
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'warehouse_id' => $this->warehouse->id,
            'shift_id' => $shift->id,
            'created_by' => $this->user->id,
            'invoice_no' => 'INV-QRIS-001',
            'status' => 'completed',
            'payment_method' => 'qris',
            'total_items' => 1,
            'total_amount' => 75000,
            'grand_total' => 75000,
            'amount_paid' => 75000,
            'payment_status' => 'paid',
        ]);

        // Add card sale
        Sale::create([
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'warehouse_id' => $this->warehouse->id,
            'shift_id' => $shift->id,
            'created_by' => $this->user->id,
            'invoice_no' => 'INV-CARD-001',
            'status' => 'completed',
            'payment_method' => 'card',
            'total_items' => 1,
            'total_amount' => 125000,
            'grand_total' => 125000,
            'amount_paid' => 125000,
            'payment_status' => 'paid',
        ]);

        $response = $this->postJson('/api/remittance/submit', [
            'shift_id' => $shift->id,
            'actual_cash' => 100000, // No cash sales → BALANCE
            'actual_qris' => 75000,  // Matches QRIS sales → BALANCE
            'actual_card' => 125000, // Matches card sales → BALANCE
        ]);

        $response->assertStatus(200);

        $this->assertEquals(0.0, (float) $response->json('data.reconciliation.difference_cash'));
        $this->assertEquals(0.0, (float) $response->json('data.reconciliation.difference_qris'));
        $this->assertEquals(0.0, (float) $response->json('data.reconciliation.difference_card'));
        $this->assertEquals('BALANCE', $response->json('data.reconciliation.cash_status'));
    }

    public function test_submit_remittance_shift_status_is_closed_after_success(): void
    {
        Sanctum::actingAs($this->user);

        $shift = $this->createOpenShift(50000.00);

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'status' => 'open']);

        $this->postJson('/api/remittance/submit', [
            'shift_id' => $shift->id,
            'actual_cash' => 50000,
            'actual_qris' => 0,
            'actual_card' => 0,
        ])->assertStatus(200);

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'status' => 'closed']);
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id, 'status' => 'open']);
    }
}
