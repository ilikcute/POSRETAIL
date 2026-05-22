<?php

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Master\Product;
use App\Models\Master\Supplier;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsignmentTaxControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_consignment_tax_api(): void
    {
        $this->getJson('/api/tax/reconciliation')->assertStatus(401);
        $this->getJson('/api/consignment/ledger')->assertStatus(401);
        $this->postJson('/api/consignment/settle', [])->assertStatus(401);
    }

    public function test_user_can_get_tax_reconciliation(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Create a purchase with tax amount (VAT Input)
        Purchase::factory()->create([
            'created_by' => $this->user->id,
            'type' => 'purchase',
            'status' => 'completed',
            'grand_total' => 111000.00,
            'tax_amount' => 11000.00,
            'created_at' => now(),
        ]);

        // 2. Create another purchase but without explicit tax (tax reconstructed as 11% inclusive)
        Purchase::factory()->create([
            'created_by' => $this->user->id,
            'type' => 'purchase',
            'status' => 'completed',
            'grand_total' => 222000.00,
            'tax_amount' => 0.00,
            'created_at' => now(),
        ]);

        // 3. Create a sale with tax amount (VAT Output)
        Sale::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'grand_total' => 555000.00,
            'tax_amount' => 55000.00,
            'created_at' => now(),
        ]);

        // 4. Create a sale without explicit tax (tax reconstructed as 11% inclusive)
        Sale::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'grand_total' => 111000.00,
            'tax_amount' => 0.00,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/tax/reconciliation?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->endOfMonth()->toDateString());

        $response->assertStatus(200)
            ->assertJsonPath('data.vat_position.status', 'UNDERPAYMENT (Harus Bayar ke Negara)');

        $this->assertEqualsWithDelta(33000, $response->json('data.vat_input_purchase.vat_input'), 0.01);
        $this->assertEqualsWithDelta(66000, $response->json('data.vat_output_sales.vat_output'), 0.01);
        $this->assertEqualsWithDelta(33000, $response->json('data.vat_position.net_vat_payable'), 0.01);
    }

    public function test_user_can_get_consignment_ledger(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'purchase_type' => 'consignment',
            'consignment_commission_fee' => 15.00, // 15% commission
        ]);

        // Establish supplier relation for this product by creating a purchase history
        $purchase = Purchase::factory()->create([
            'created_by' => $this->user->id,
            'supplier_id' => $supplier->id,
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 10,
            'unit_cost' => 10000.00,
            'subtotal' => 100000.00,
        ]);

        // Create a completed sale of this product
        $sale = Sale::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'purchase_type' => 'consignment',
            'qty' => 2,
            'unit_price' => 50000.00,
            'subtotal' => 100000.00, // gross revenue
            'consignment_commission_amount' => 15000.00, // 15% of 100000
            'consignment_payable_amount' => 85000.00, // 85% of 100000
        ]);

        $response = $this->getJson('/api/consignment/ledger?supplier_id='.$supplier->id);

        $response->assertStatus(200);

        $this->assertEquals(2, $response->json('data.summary.total_items_sold'));
        $this->assertEqualsWithDelta(100000.00, $response->json('data.summary.total_gross_revenue'), 0.01);
        $this->assertEqualsWithDelta(15000.00, $response->json('data.summary.total_store_commission'), 0.01);
        $this->assertEqualsWithDelta(85000.00, $response->json('data.summary.total_supplier_payable'), 0.01);
    }

    public function test_user_cannot_settle_consignment_if_supplier_inactive(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'is_active' => false,
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 10000.00,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'bank_account_code' => '1102',
            'payment_amount' => 5000.00,
        ];

        $response = $this->postJson('/api/consignment/settle', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Supplier {$supplier->name} sedang tidak aktif.");
    }

    public function test_user_cannot_settle_consignment_if_bank_account_inactive(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'is_active' => true,
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => false,
            'balance' => 10000.00,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'bank_account_code' => '1102',
            'payment_amount' => 5000.00,
        ];

        $response = $this->postJson('/api/consignment/settle', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Akun Kas/Bank dengan kode 1102 sedang tidak aktif.');
    }

    public function test_user_cannot_settle_consignment_if_balance_insufficient(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'is_active' => true,
        ]);

        Account::factory()->create([
            'code' => '1102',
            'is_active' => true,
            'balance' => 1000.00,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'bank_account_code' => '1102',
            'payment_amount' => 5000.00,
        ];

        $response = $this->postJson('/api/consignment/settle', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Saldo Kas/Bank tidak mencukupi untuk melakukan pembayaran pelunasan. Saldo saat ini: Rp 1.000,00');
    }

    public function test_user_can_settle_consignment_successfully(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'is_active' => true,
        ]);

        $bankAccount = Account::factory()->create([
            'code' => '1102',
            'name' => 'Bank Mandiri Toko',
            'type' => 'asset',
            'is_active' => true,
            'balance' => 10000.00,
        ]);

        $consignmentAccount = Account::factory()->create([
            'code' => '2102',
            'name' => 'Hutang Konsinyasi (Consignment Payable)',
            'type' => 'liability',
            'is_active' => true,
            'balance' => 8000.00,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'bank_account_code' => '1102',
            'payment_amount' => 5000.00,
            'notes' => 'Settle May sales',
        ];

        $response = $this->postJson('/api/consignment/settle', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.supplier_name', $supplier->name)
            ->assertJsonPath('data.bank_account', 'Bank Mandiri Toko');

        $this->assertEquals(5000.00, $response->json('data.amount_paid'));

        // Check balances
        $this->assertEquals(5000.00, $bankAccount->refresh()->balance);
        $this->assertEquals(3000.00, $consignmentAccount->refresh()->balance);

        // Check journal entries
        $this->assertDatabaseHas('journal_entries', [
            'description' => "Settle May sales ke [Supplier: {$supplier->name}]",
        ]);

        $entry = JournalEntry::where('description', "Settle May sales ke [Supplier: {$supplier->name}]")->first();
        $this->assertNotNull($entry);

        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $entry->id,
            'account_id' => $consignmentAccount->id,
            'debit' => 5000.00,
            'credit' => 0.00,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $entry->id,
            'account_id' => $bankAccount->id,
            'debit' => 0.00,
            'credit' => 5000.00,
        ]);
    }
}
