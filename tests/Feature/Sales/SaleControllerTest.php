<?php

namespace Tests\Feature\Sales;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Inventory\ProductStock;
use App\Models\Master\Product;
use App\Models\Master\Station;
use App\Models\Master\Store;
use App\Models\Master\Warehouse;
use App\Models\Sales\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Station $station;

    protected Warehouse $warehouse;

    protected Product $product;

    protected Account $cashAccount;

    protected Account $salesAccount;

    protected Account $inventoryAccount;

    protected Account $hppAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create standard master models
        $this->user = User::factory()->create();
        $this->store = Store::factory()->create();
        $this->station = Station::factory()->create();
        $this->warehouse = Warehouse::factory()->create();

        // 2. Create the required Accounts for double-entry journal postings
        $this->cashAccount = Account::create([
            'code' => '1101',
            'name' => 'Kas Laci POS',
            'type' => 'asset',
            'balance' => 0.00,
            'description' => 'Kas Laci',
        ]);

        Account::create([
            'code' => '1102',
            'name' => 'Bank POS',
            'type' => 'asset',
            'balance' => 0.00,
            'description' => 'Bank POS Account',
        ]);

        $this->salesAccount = Account::create([
            'code' => '4101',
            'name' => 'Pendapatan Penjualan',
            'type' => 'revenue',
            'balance' => 0.00,
            'description' => 'Pendapatan Penjualan',
        ]);

        $this->inventoryAccount = Account::create([
            'code' => '1201',
            'name' => 'Persediaan Barang Dagang',
            'type' => 'asset',
            'balance' => 1000000.00,
            'description' => 'Persediaan',
        ]);

        $this->hppAccount = Account::create([
            'code' => '5101',
            'name' => 'Harga Pokok Penjualan',
            'type' => 'expense',
            'balance' => 0.00,
            'description' => 'HPP',
        ]);

        // Standard test product
        $this->product = Product::factory()->create([
            'cost_price' => 10000.00,
            'price' => 15000.00,
            'is_taxable' => false,
        ]);
    }

    public function test_guest_cannot_access_sales_api(): void
    {
        $this->getJson('/api/sales')->assertStatus(401);
        $this->postJson('/api/sales', [])->assertStatus(401);
        $this->getJson('/api/sales/1')->assertStatus(401);
        $this->putJson('/api/sales/1', [])->assertStatus(401);
        $this->deleteJson('/api/sales/1')->assertStatus(401);
    }

    public function test_checkout_fails_if_no_active_shift(): void
    {
        Sanctum::actingAs($this->user);

        // Seed stock so stock validation passes
        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 10.00,
        ]);

        $payload = [
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 2,
                    'unit_price' => 15000.00,
                    'cost_price' => 10000.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Gagal checkout! Tidak ada shift aktif untuk kasir ini.');
    }

    public function test_checkout_fails_if_stock_insufficient(): void
    {
        Sanctum::actingAs($this->user);

        // Open shift for cashier
        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 100000.00,
            'status' => 'open',
        ]);

        // Empty stock setup
        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 0.00, // Out of stock!
        ]);

        $payload = [
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'shift_id' => $shift->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 1,
                    'unit_price' => 15000.00,
                    'cost_price' => 10000.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Stok produk '{$this->product->name}' telah habis di gudang terpilih.");
    }

    public function test_checkout_fails_if_stock_qty_is_not_enough(): void
    {
        Sanctum::actingAs($this->user);

        // Open shift for cashier
        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 100000.00,
            'status' => 'open',
        ]);

        // Limited stock setup
        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 3.00,
        ]);

        $payload = [
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'shift_id' => $shift->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 5, // Exceeds stock count!
                    'unit_price' => 15000.00,
                    'cost_price' => 10000.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Stok produk '{$this->product->name}' tidak mencukupi. Tersedia: 3, Diminta: 5.");
    }

    public function test_checkout_succeeds_with_valid_data_and_active_shift(): void
    {
        Sanctum::actingAs($this->user);

        // Open shift for cashier
        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 100000.00,
            'status' => 'open',
        ]);

        // Seed stock
        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 10.00,
        ]);

        $payload = [
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'shift_id' => $shift->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'amount_paid' => 30000.00,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 2,
                    'unit_price' => 15000.00,
                    'cost_price' => 10000.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Transaksi penjualan berhasil diproses.');

        // Verify stock has been subtracted
        $stock = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(8.00, (float) $stock->qty);

        // Verify standard double-entry journal entries
        $this->assertDatabaseHas('journal_entries', [
            'created_by' => $this->user->id,
        ]);
    }

    public function test_checkout_fails_if_simulated_payment_gateway_fails(): void
    {
        Sanctum::actingAs($this->user);

        // Open shift for cashier
        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 100000.00,
            'status' => 'open',
        ]);

        // Seed stock
        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 10.00,
        ]);

        $payload = [
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'shift_id' => $shift->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'payment_method' => 'card',
            'card_number' => '4111111111111112', // Magic decline number trigger!
            'amount_paid' => 15000.00,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 1,
                    'unit_price' => 15000.00,
                    'cost_price' => 10000.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(402)
            ->assertJsonPath('message', 'Transaksi pembayaran senilai Rp 15.000 via Payment Gateway ditolak.');

        // Stock must NOT be deducted (rolled back!)
        $stock = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(10.00, (float) $stock->qty);
    }

    public function test_void_completed_sale_restores_stock_and_reverses_ledger(): void
    {
        Sanctum::actingAs($this->user);

        // Open shift for cashier
        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 100000.00,
            'status' => 'open',
        ]);

        // Seed stock
        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 10.00,
        ]);

        // 1. Checkout transaction
        $payload = [
            'store_id' => $this->store->id,
            'station_id' => $this->station->id,
            'shift_id' => $shift->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'amount_paid' => 15000.00,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 1,
                    'unit_price' => 15000.00,
                    'cost_price' => 10000.00,
                ],
            ],
        ];

        $checkoutResponse = $this->postJson('/api/sales', $payload);
        $checkoutResponse->assertStatus(201);
        $saleId = $checkoutResponse->json('data.id');

        // Check stock is 9.00
        $stock = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(9.00, (float) $stock->qty);

        // 2. Void transaction (Update status => void)
        $voidResponse = $this->putJson("/api/sales/{$saleId}", [
            'status' => 'void',
        ]);

        $voidResponse->assertStatus(200);

        // Check stock is restored to 10.00
        $stockRestored = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(10.00, (float) $stockRestored->qty);

        // Verify correction/reversal journal entry is posted
        $this->assertDatabaseHas('journal_entries', [
            'description' => 'Jurnal Koreksi / Pembatalan Penjualan POS - Nota #'.$checkoutResponse->json('data.invoice_no'),
        ]);
    }
}
