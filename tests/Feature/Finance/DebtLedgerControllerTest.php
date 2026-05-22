<?php

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Master\Customer;
use App\Models\Master\Supplier;
use App\Models\Purchase\Purchase;
use App\Models\Sales\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DebtLedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_debt_ledger_apis(): void
    {
        $this->getJson('/api/debt/ap-ledger')->assertStatus(401);
        $this->getJson('/api/debt/ap-aging')->assertStatus(401);
        $this->postJson('/api/debt/pay-ap', [])->assertStatus(401);

        $this->getJson('/api/debt/ar-ledger')->assertStatus(401);
        $this->getJson('/api/debt/ar-aging')->assertStatus(401);
        $this->postJson('/api/debt/receive-ar', [])->assertStatus(401);
    }

    public function test_user_can_get_ap_ledger(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create(['name' => 'Supplier Test']);

        Purchase::factory()->create([
            'created_by' => $this->user->id,
            'supplier_id' => $supplier->id,
            'type' => 'purchase',
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'grand_total' => 500000,
            'amount_paid' => 100000,
        ]);

        $response = $this->getJson('/api/debt/ap-ledger');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_invoices_outstanding', 1)
            ->assertJsonPath('data.summary.total_outstanding_balance', 400000);
    }

    public function test_user_can_get_ap_aging_report(): void
    {
        Sanctum::actingAs($this->user);

        Purchase::factory()->create([
            'created_by' => $this->user->id,
            'type' => 'purchase',
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'grand_total' => 200000,
            'amount_paid' => 50000,
            'created_at' => now(), // current (0-30 days)
        ]);

        Purchase::factory()->create([
            'created_by' => $this->user->id,
            'type' => 'purchase',
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'grand_total' => 300000,
            'amount_paid' => 0,
            'created_at' => now()->subDays(45), // 31-60 days
        ]);

        $response = $this->getJson('/api/debt/ap-aging');

        $response->assertStatus(200)
            ->assertJsonPath('data.buckets.current', 150000)
            ->assertJsonPath('data.buckets.aging_31_60', 300000);
    }

    public function test_user_cannot_pay_ap_if_purchase_status_not_completed(): void
    {
        Sanctum::actingAs($this->user);

        $purchase = Purchase::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'pending',
            'grand_total' => 500000,
            'amount_paid' => 0,
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 600000,
        ]);

        $payload = [
            'purchase_id' => $purchase->id,
            'payment_amount' => 200000,
            'bank_account_code' => '1102',
        ];

        $response = $this->postJson('/api/debt/pay-ap', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Pembelian dengan Ref {$purchase->reference_no} tidak dalam status completed.");
    }

    public function test_user_cannot_pay_ap_if_supplier_inactive(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'is_active' => false,
        ]);

        $purchase = Purchase::factory()->create([
            'created_by' => $this->user->id,
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'grand_total' => 500000,
            'amount_paid' => 0,
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 600000,
        ]);

        $payload = [
            'purchase_id' => $purchase->id,
            'payment_amount' => 200000,
            'bank_account_code' => '1102',
        ];

        $response = $this->postJson('/api/debt/pay-ap', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Supplier {$supplier->name} sedang tidak aktif.");
    }

    public function test_user_cannot_pay_ap_if_bank_account_inactive(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'is_active' => true,
        ]);

        $purchase = Purchase::factory()->create([
            'created_by' => $this->user->id,
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'grand_total' => 500000,
            'amount_paid' => 0,
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => false,
            'balance' => 600000,
        ]);

        $payload = [
            'purchase_id' => $purchase->id,
            'payment_amount' => 200000,
            'bank_account_code' => '1102',
        ];

        $response = $this->postJson('/api/debt/pay-ap', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Akun Kas/Bank dengan kode 1102 sedang tidak aktif.');
    }

    public function test_user_cannot_pay_ap_if_payment_exceeds_outstanding(): void
    {
        Sanctum::actingAs($this->user);

        $purchase = Purchase::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'grand_total' => 500000,
            'amount_paid' => 300000, // outstanding is 200000
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 600000,
        ]);

        $payload = [
            'purchase_id' => $purchase->id,
            'payment_amount' => 250000, // exceeds 200000
            'bank_account_code' => '1102',
        ];

        $response = $this->postJson('/api/debt/pay-ap', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Jumlah pembayaran (Rp 250.000) melebihi sisa utang (Rp 200.000)');
    }

    public function test_user_cannot_pay_ap_if_balance_insufficient(): void
    {
        Sanctum::actingAs($this->user);

        $purchase = Purchase::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'grand_total' => 500000,
            'amount_paid' => 0,
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 15000, // insufficient for 200000
        ]);

        $payload = [
            'purchase_id' => $purchase->id,
            'payment_amount' => 200000,
            'bank_account_code' => '1102',
        ];

        $response = $this->postJson('/api/debt/pay-ap', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Saldo Kas/Bank tidak mencukupi untuk melakukan pembayaran. Saldo saat ini: Rp 15.000,00');
    }

    public function test_user_can_pay_ap_successfully(): void
    {
        Sanctum::actingAs($this->user);

        $purchase = Purchase::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'grand_total' => 500000,
            'amount_paid' => 100000, // outstanding: 400000
            'payment_status' => 'partial',
        ]);

        $cashAccount = Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 600000,
        ]);

        // create liability account code 2101
        $apAccount = Account::factory()->create([
            'code' => '2101',
            'is_active' => true,
            'balance' => 1000000,
        ]);

        $payload = [
            'purchase_id' => $purchase->id,
            'payment_amount' => 300000,
            'bank_account_code' => '1102',
            'notes' => 'Bayar cicilan pembelian',
        ];

        $response = $this->postJson('/api/debt/pay-ap', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.new_amount_paid', 400000)
            ->assertJsonPath('data.outstanding_debt', 100000)
            ->assertJsonPath('data.payment_status', 'partial');

        // Check database state
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'amount_paid' => 400000,
            'payment_status' => 'partial',
        ]);

        $this->assertEquals(300000, $cashAccount->fresh()->balance * -1 + 600000); // 300000 decremented
        $this->assertEquals(700000, $apAccount->fresh()->balance); // 1000000 - 300000

        // Check journal entries
        $journal = JournalEntry::where('reference_no', $response->json('data.journal_entry'))->first();
        $this->assertNotNull($journal);

        // Check journal items
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $journal->id,
            'account_id' => $apAccount->id,
            'debit' => 300000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $journal->id,
            'account_id' => $cashAccount->id,
            'debit' => 0,
            'credit' => 300000,
        ]);
    }

    public function test_user_can_get_ar_ledger(): void
    {
        Sanctum::actingAs($this->user);

        $customer = Customer::factory()->create(['name' => 'Pelanggan Test']);

        Sale::factory()->create([
            'created_by' => $this->user->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'grand_total' => 700000,
            'amount_paid' => 200000,
        ]);

        $response = $this->getJson('/api/debt/ar-ledger');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_invoices_outstanding', 1)
            ->assertJsonPath('data.summary.total_outstanding_balance', 500000);
    }

    public function test_user_can_get_ar_aging_report(): void
    {
        Sanctum::actingAs($this->user);

        Sale::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'grand_total' => 250000,
            'amount_paid' => 50000,
            'created_at' => now(), // current (0-30 days)
        ]);

        Sale::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'grand_total' => 400000,
            'amount_paid' => 0,
            'created_at' => now()->subDays(75), // 61-90 days
        ]);

        $response = $this->getJson('/api/debt/ar-aging');

        $response->assertStatus(200)
            ->assertJsonPath('data.buckets.current', 200000)
            ->assertJsonPath('data.buckets.aging_61_90', 400000);
    }

    public function test_user_cannot_receive_ar_if_sale_status_not_completed(): void
    {
        Sanctum::actingAs($this->user);

        $sale = Sale::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'pending',
            'grand_total' => 500000,
            'amount_paid' => 0,
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 600000,
        ]);

        $payload = [
            'sale_id' => $sale->id,
            'payment_amount' => 200000,
            'bank_account_code' => '1102',
        ];

        $response = $this->postJson('/api/debt/receive-ar', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Penjualan dengan Invoice {$sale->invoice_no} tidak dalam status completed.");
    }

    public function test_user_cannot_receive_ar_if_customer_inactive(): void
    {
        Sanctum::actingAs($this->user);

        $customer = Customer::factory()->create([
            'is_active' => false,
        ]);

        $sale = Sale::factory()->create([
            'created_by' => $this->user->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'grand_total' => 500000,
            'amount_paid' => 0,
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 600000,
        ]);

        $payload = [
            'sale_id' => $sale->id,
            'payment_amount' => 200000,
            'bank_account_code' => '1102',
        ];

        $response = $this->postJson('/api/debt/receive-ar', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Pelanggan {$customer->name} sedang tidak aktif.");
    }

    public function test_user_cannot_receive_ar_if_receipt_exceeds_outstanding(): void
    {
        Sanctum::actingAs($this->user);

        $sale = Sale::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'grand_total' => 500000,
            'amount_paid' => 400000, // outstanding is 100000
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 600000,
        ]);

        $payload = [
            'sale_id' => $sale->id,
            'payment_amount' => 150000, // exceeds 100000
            'bank_account_code' => '1102',
        ];

        $response = $this->postJson('/api/debt/receive-ar', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Jumlah pelunasan (Rp 150.000) melebihi sisa piutang (Rp 100.000)');
    }

    public function test_user_can_receive_ar_successfully(): void
    {
        Sanctum::actingAs($this->user);

        $sale = Sale::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'grand_total' => 500000,
            'amount_paid' => 100000, // outstanding: 400000
            'payment_status' => 'partial',
        ]);

        $cashAccount = Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 600000,
        ]);

        // create asset account code 1103 (Piutang Dagang)
        $arAccount = Account::factory()->create([
            'code' => '1103',
            'is_active' => true,
            'balance' => 1000000,
        ]);

        $payload = [
            'sale_id' => $sale->id,
            'payment_amount' => 300000,
            'bank_account_code' => '1102',
            'notes' => 'Pelunasan piutang invoice',
        ];

        $response = $this->postJson('/api/debt/receive-ar', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.new_amount_paid', 400000)
            ->assertJsonPath('data.outstanding_receivable', 100000)
            ->assertJsonPath('data.payment_status', 'partial');

        // Check database state
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'amount_paid' => 400000,
            'payment_status' => 'partial',
        ]);

        $this->assertEquals(900000, $cashAccount->fresh()->balance); // 600000 + 300000
        $this->assertEquals(700000, $arAccount->fresh()->balance); // 1000000 - 300000 (asset Piutang Dagang decreases)

        // Check journal entries
        $journal = JournalEntry::where('reference_no', $response->json('data.journal_entry'))->first();
        $this->assertNotNull($journal);

        // Check journal items
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $journal->id,
            'account_id' => $cashAccount->id,
            'debit' => 300000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $journal->id,
            'account_id' => $arAccount->id,
            'debit' => 0,
            'credit' => 300000,
        ]);
    }
}
