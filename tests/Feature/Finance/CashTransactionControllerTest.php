<?php

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Finance\CashTransaction;
use App\Models\Master\Station;
use App\Models\Master\Store;
use App\Models\Sales\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Station $station;

    protected Account $cashAccount;

    protected Account $bankAccount;

    protected Account $revenueAccount;

    protected Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create();
        $this->station = Station::factory()->create();

        // Setup the required system accounts with initial balances
        $this->cashAccount = Account::factory()->create([
            'code' => '1101',
            'name' => 'Kas Toko / Petty Cash',
            'type' => 'asset',
            'balance' => 1000000.00,
        ]);

        $this->bankAccount = Account::factory()->create([
            'code' => '1102',
            'name' => 'Bank Mandiri Toko',
            'type' => 'asset',
            'balance' => 5000000.00,
        ]);

        $this->revenueAccount = Account::factory()->create([
            'code' => '4201',
            'name' => 'Pendapatan Lain-lain',
            'type' => 'revenue',
            'balance' => 0.00,
        ]);

        $this->expenseAccount = Account::factory()->create([
            'code' => '5201',
            'name' => 'Beban Listrik Toko',
            'type' => 'expense',
            'balance' => 0.00,
        ]);
    }

    public function test_guest_cannot_access_cash_transactions_api(): void
    {
        $this->getJson('/api/cash-transactions')->assertStatus(401);
        $this->postJson('/api/cash-transactions', [])->assertStatus(401);
        $this->getJson('/api/cash-transactions/1')->assertStatus(401);
        $this->putJson('/api/cash-transactions/1', [])->assertStatus(401);
        $this->deleteJson('/api/cash-transactions/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_cash_transactions_list(): void
    {
        Sanctum::actingAs($this->user);

        CashTransaction::factory()->create([
            'store_id' => $this->store->id,
            'type' => 'in',
            'amount' => 100.00,
            'category' => 'Lainnya',
            'payment_method' => 'cash',
        ]);

        $response = $this->getJson('/api/cash-transactions');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.type', 'in');
    }

    public function test_user_can_create_cash_in_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'store_id' => $this->store->id,
            'type' => 'in',
            'amount' => 200000.00,
            'category' => 'Penjualan Kardus Bekas',
            'payment_method' => 'cash',
            'description' => 'Hasil penjualan kardus bekas gudang',
        ];

        $response = $this->postJson('/api/cash-transactions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', '200000.00')
            ->assertJsonPath('data.type', 'in');

        // Check if Cash account balance increased
        $this->assertEquals(1200000.00, $this->cashAccount->refresh()->balance);

        // Check if Revenue account balance increased
        $this->assertEquals(200000.00, $this->revenueAccount->refresh()->balance);

        // Check Journal Entry
        $this->assertDatabaseHas('journal_entries', [
            'reference_no' => 'JV-CASH-000002', // id should be 2 because factory created one transaction
        ]);
    }

    public function test_user_can_create_cash_out_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'store_id' => $this->store->id,
            'type' => 'out',
            'amount' => 150000.00,
            'category' => 'Beban Listrik',
            'payment_method' => 'cash',
            'description' => 'Pembelian token listrik toko',
        ];

        $response = $this->postJson('/api/cash-transactions', $payload);

        $response->assertStatus(201);

        // Check if Cash account balance decreased
        $this->assertEquals(850000.00, $this->cashAccount->refresh()->balance);

        // Check if Expense account balance increased
        $this->assertEquals(150000.00, $this->expenseAccount->refresh()->balance);
    }

    public function test_user_cannot_create_cash_out_if_balance_insufficient(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'store_id' => $this->store->id,
            'type' => 'out',
            'amount' => 1500000.00, // Exceeds cash balance of 1000000
            'category' => 'Beban Listrik',
            'payment_method' => 'cash',
            'description' => 'Pembelian token listrik toko besar',
        ];

        $response = $this->postJson('/api/cash-transactions', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Saldo Kas Toko / Petty Cash tidak mencukupi untuk melakukan transaksi keluar ini.');
    }

    public function test_cannot_create_transaction_for_closed_shift(): void
    {
        Sanctum::actingAs($this->user);

        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'starting_cash' => 500000.00,
            'status' => 'closed',
        ]);

        $payload = [
            'store_id' => $this->store->id,
            'shift_id' => $shift->id,
            'type' => 'in',
            'amount' => 50000.00,
            'category' => 'Lainnya',
            'payment_method' => 'cash',
        ];

        $response = $this->postJson('/api/cash-transactions', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tidak dapat membuat transaksi kas pada shift yang sudah ditutup.');
    }

    public function test_user_can_update_cash_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = CashTransaction::factory()->create([
            'store_id' => $this->store->id,
            'type' => 'in',
            'amount' => 100000.00,
            'payment_method' => 'cash',
        ]);

        // Post original journal entry
        $response = $this->putJson("/api/cash-transactions/{$transaction->id}", [
            'store_id' => $this->store->id,
            'type' => 'in',
            'amount' => 150000.00, // Update amount
            'category' => 'Updated Category',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200);

        // Kas balance should be 1000000 + 150000 = 1150000 (after reversing the old 100000 and adding new 150000)
        $this->assertEquals(1150000.00, $this->cashAccount->refresh()->balance);
    }

    public function test_user_can_delete_cash_transaction_reverts_journal_and_balance(): void
    {
        Sanctum::actingAs($this->user);

        // We create transaction using API so it sets up the journal entries
        $payload = [
            'store_id' => $this->store->id,
            'type' => 'in',
            'amount' => 200000.00,
            'category' => 'Lain-lain',
            'payment_method' => 'cash',
        ];

        $createResponse = $this->postJson('/api/cash-transactions', $payload);
        $createResponse->assertStatus(201);
        $transactionId = $createResponse->json('data.id');

        // Check that balance has increased
        $this->assertEquals(1200000.00, $this->cashAccount->refresh()->balance);

        // Now delete it
        $deleteResponse = $this->deleteJson("/api/cash-transactions/{$transactionId}");
        $deleteResponse->assertStatus(200);

        // Check that balance reverted to original 1000000.00
        $this->assertEquals(1000000.00, $this->cashAccount->refresh()->balance);

        // Check that journal entry was deleted
        $this->assertDatabaseMissing('journal_entries', [
            'reference_no' => 'JV-CASH-'.str_pad($transactionId, 6, '0', STR_PAD_LEFT),
        ]);
    }
}
