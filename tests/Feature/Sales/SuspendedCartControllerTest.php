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
use App\Models\Sales\SuspendedCart;
use App\Models\Sales\SuspendedCartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuspendedCartControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Station $station;

    protected Warehouse $warehouse;

    protected Product $product;

    protected Account $cashAccount;

    protected Account $bankAccount;

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

        $this->bankAccount = Account::create([
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

        // 3. Standard test product
        $this->product = Product::factory()->create([
            'cost_price' => 10000.00,
            'price' => 15000.00,
            'is_taxable' => false,
        ]);
    }

    // =========================================================================
    // 1. GUEST ACCESS RESTRICTION TESTS
    // =========================================================================

    public function test_guest_cannot_suspend_cart(): void
    {
        $this->postJson('/api/suspended-carts/suspend', [])->assertStatus(401);
    }

    public function test_guest_cannot_get_pending_carts(): void
    {
        $this->getJson('/api/suspended-carts/pending')->assertStatus(401);
    }

    public function test_guest_cannot_retrieve_cart_by_queue_code(): void
    {
        $this->getJson('/api/suspended-carts/retrieve/Q-20260521-0001')->assertStatus(401);
    }

    public function test_guest_cannot_complete_checkout(): void
    {
        $this->postJson('/api/suspended-carts/complete', [])->assertStatus(401);
    }

    public function test_guest_cannot_void_cart(): void
    {
        $this->putJson('/api/suspended-carts/void/Q-20260521-0001')->assertStatus(401);
    }

    public function test_guest_cannot_reset_carts(): void
    {
        $this->postJson('/api/suspended-carts/reset')->assertStatus(401);
    }

    // =========================================================================
    // 2. SUSPEND CART (HOLD) TESTS
    // =========================================================================

    public function test_authenticated_user_can_suspend_cart_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'station_id' => $this->station->id,
            'notes' => 'Pelanggan meminta tunggu sebentar',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 2,
                    'unit_price' => 15000.00,
                    'notes' => null,
                ],
            ],
        ];

        $response = $this->postJson('/api/suspended-carts/suspend', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Cart suspended / Keranjang belanja digantung successfully')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'queue_code',
                    'station_id',
                    'status',
                    'total_items',
                    'total_amount',
                    'items',
                ],
            ]);

        // Verify queue code format Q-YYYYMMDD-XXXX
        $queueCode = $response->json('data.queue_code');
        $this->assertMatchesRegularExpression('/^Q-\d{8}-\d{4}$/', $queueCode);

        // Verify the cart is persisted in the database
        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => $queueCode,
            'station_id' => $this->station->id,
            'status' => 'pending',
            'total_items' => 2,
            'total_amount' => 30000.00,
        ]);

        // Verify cart items are persisted
        $this->assertDatabaseHas('suspended_cart_items', [
            'product_id' => $this->product->id,
            'qty' => 2,
            'unit_price' => 15000.00,
            'subtotal' => 30000.00,
        ]);
    }

    public function test_suspend_cart_requires_station_id(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/suspended-carts/suspend', [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1, 'unit_price' => 15000],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['station_id']);
    }

    public function test_suspend_cart_requires_at_least_one_item(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/suspended-carts/suspend', [
            'station_id' => $this->station->id,
            'items' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_suspend_cart_queue_code_increments_sequentially(): void
    {
        Sanctum::actingAs($this->user);

        $itemPayload = [
            'station_id' => $this->station->id,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1, 'unit_price' => 15000],
            ],
        ];

        $response1 = $this->postJson('/api/suspended-carts/suspend', $itemPayload);
        $response2 = $this->postJson('/api/suspended-carts/suspend', $itemPayload);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $code1 = $response1->json('data.queue_code');
        $code2 = $response2->json('data.queue_code');

        // Both codes must follow format
        $this->assertMatchesRegularExpression('/^Q-\d{8}-\d{4}$/', $code1);
        $this->assertMatchesRegularExpression('/^Q-\d{8}-\d{4}$/', $code2);

        // The codes must be different (sequential)
        $this->assertNotEquals($code1, $code2);

        // Second code counter should be higher than first
        $counter1 = (int) substr($code1, -4);
        $counter2 = (int) substr($code2, -4);
        $this->assertGreaterThan($counter1, $counter2);
    }

    // =========================================================================
    // 3. GET PENDING CARTS TESTS
    // =========================================================================

    public function test_can_get_all_pending_carts(): void
    {
        Sanctum::actingAs($this->user);

        // Create two pending carts manually
        $cart1 = SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'pending',
        ]);
        SuspendedCartItem::create([
            'suspended_cart_id' => $cart1->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'unit_price' => 15000,
            'subtotal' => 15000,
        ]);

        $cart2 = SuspendedCart::create([
            'queue_code' => 'Q-20260521-0002',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 2,
            'total_amount' => 30000,
            'status' => 'pending',
        ]);
        SuspendedCartItem::create([
            'suspended_cart_id' => $cart2->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'unit_price' => 15000,
            'subtotal' => 30000,
        ]);

        $response = $this->getJson('/api/suspended-carts/pending');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pending suspended carts retrieved successfully')
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'queue_code',
                        'status',
                        'items',
                        'station',
                    ],
                ],
            ]);
    }

    public function test_pending_carts_excludes_completed_and_voided_carts(): void
    {
        Sanctum::actingAs($this->user);

        // Create one pending and one completed cart
        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'pending',
        ]);

        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0002',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'completed',
        ]);

        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0003',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'void',
        ]);

        $response = $this->getJson('/api/suspended-carts/pending');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // =========================================================================
    // 4. RETRIEVE CART BY QUEUE CODE TESTS
    // =========================================================================

    public function test_can_retrieve_pending_cart_by_queue_code(): void
    {
        Sanctum::actingAs($this->user);

        $cart = SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'pending',
        ]);
        SuspendedCartItem::create([
            'suspended_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'unit_price' => 15000,
            'subtotal' => 15000,
        ]);

        $response = $this->getJson('/api/suspended-carts/retrieve/Q-20260521-0001');

        $response->assertStatus(200)
            ->assertJsonPath('data.queue_code', 'Q-20260521-0001')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['items']]);
    }

    public function test_retrieve_cart_returns_404_for_unknown_queue_code(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/suspended-carts/retrieve/Q-99999999-9999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Keranjang belanja gantung tidak ditemukan atau sudah diselesaikan!');
    }

    public function test_retrieve_cart_returns_404_for_completed_cart(): void
    {
        Sanctum::actingAs($this->user);

        // Create a completed cart (already processed)
        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0099',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/suspended-carts/retrieve/Q-20260521-0099');

        $response->assertStatus(404);
    }

    // =========================================================================
    // 5. COMPLETE CHECKOUT TESTS
    // =========================================================================

    private function createPendingCartWithStock(int $stockQty = 10): array
    {
        $cart = SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 2,
            'total_amount' => 30000,
            'status' => 'pending',
        ]);

        SuspendedCartItem::create([
            'suspended_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'unit_price' => 15000,
            'subtotal' => 30000,
        ]);

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => $stockQty,
        ]);

        $shift = Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 100000.00,
            'status' => 'open',
        ]);

        return [$cart, $shift];
    }

    public function test_complete_checkout_fails_when_no_active_shift_on_target_station(): void
    {
        Sanctum::actingAs($this->user);

        // Create a cart without creating a shift
        $cart = SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'pending',
        ]);

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 10,
        ]);

        $response = $this->postJson('/api/suspended-carts/complete', [
            'queue_code' => 'Q-20260521-0001',
            'target_station_id' => $this->station->id,
            'payment_method' => 'cash',
            'bank_account_code' => '1102',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Gagal checkout! Stasiun kasir target tidak memiliki shift aktif.');

        // Cart must remain pending
        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0001',
            'status' => 'pending',
        ]);
    }

    public function test_complete_checkout_fails_when_stock_is_zero(): void
    {
        Sanctum::actingAs($this->user);

        $cart = SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 2,
            'total_amount' => 30000,
            'status' => 'pending',
        ]);

        SuspendedCartItem::create([
            'suspended_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'unit_price' => 15000,
            'subtotal' => 30000,
        ]);

        // Out of stock!
        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 0,
        ]);

        Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 100000.00,
            'status' => 'open',
        ]);

        $response = $this->postJson('/api/suspended-carts/complete', [
            'queue_code' => 'Q-20260521-0001',
            'target_station_id' => $this->station->id,
            'payment_method' => 'cash',
            'bank_account_code' => '1102',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Stok produk '{$this->product->name}' telah habis di gudang terpilih.");

        // Cart must remain pending (not checked out)
        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0001',
            'status' => 'pending',
        ]);
    }

    public function test_complete_checkout_fails_when_stock_is_insufficient(): void
    {
        Sanctum::actingAs($this->user);

        $cart = SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 5,
            'total_amount' => 75000,
            'status' => 'pending',
        ]);

        SuspendedCartItem::create([
            'suspended_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'qty' => 5, // Requires 5 but only 3 available
            'unit_price' => 15000,
            'subtotal' => 75000,
        ]);

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 3, // Only 3 in stock
        ]);

        Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 100000.00,
            'status' => 'open',
        ]);

        $response = $this->postJson('/api/suspended-carts/complete', [
            'queue_code' => 'Q-20260521-0001',
            'target_station_id' => $this->station->id,
            'payment_method' => 'cash',
            'bank_account_code' => '1102',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Stok produk '{$this->product->name}' tidak mencukupi. Tersedia: 3, Diminta: 5.");
    }

    public function test_complete_checkout_fails_on_payment_gateway_decline(): void
    {
        Sanctum::actingAs($this->user);

        [$cart, $shift] = $this->createPendingCartWithStock(10);

        $response = $this->postJson('/api/suspended-carts/complete', [
            'queue_code' => 'Q-20260521-0001',
            'target_station_id' => $this->station->id,
            'payment_method' => 'card',
            'card_number' => '4111111111111112', // Magic decline number
            'bank_account_code' => '1102',
        ]);

        $response->assertStatus(402);

        // Stock must NOT be deducted on payment failure
        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(10.00, (float) $stock->qty);

        // Cart must remain pending
        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0001',
            'status' => 'pending',
        ]);
    }

    public function test_complete_checkout_succeeds_with_cash_payment(): void
    {
        Sanctum::actingAs($this->user);

        [$cart, $shift] = $this->createPendingCartWithStock(10);

        $response = $this->postJson('/api/suspended-carts/complete', [
            'queue_code' => 'Q-20260521-0001',
            'target_station_id' => $this->station->id,
            'payment_method' => 'cash',
            'bank_account_code' => '1102',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Checkout from suspended cart completed and journalized successfully')
            ->assertJsonStructure([
                'data' => [
                    'sale_id',
                    'invoice_no',
                    'queue_code',
                    'grand_total',
                    'payment_method',
                ],
            ]);

        // Cart status must be updated to 'completed'
        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0001',
            'status' => 'completed',
        ]);

        // Stock must be deducted (10 - 2 = 8)
        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(8.00, (float) $stock->qty);

        // A sale record must have been created
        $this->assertDatabaseHas('sales', [
            'station_id' => $this->station->id,
            'shift_id' => $shift->id,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Journal entries must be posted
        $this->assertDatabaseHas('journal_entries', [
            'created_by' => $this->user->id,
        ]);
    }

    public function test_complete_checkout_succeeds_with_qris_payment(): void
    {
        Sanctum::actingAs($this->user);

        [$cart, $shift] = $this->createPendingCartWithStock(10);

        $response = $this->postJson('/api/suspended-carts/complete', [
            'queue_code' => 'Q-20260521-0001',
            'target_station_id' => $this->station->id,
            'payment_method' => 'qris',
            'bank_account_code' => '1102',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_method', 'qris');

        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0001',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('sales', [
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);
    }

    public function test_complete_checkout_returns_404_for_nonexistent_queue_code(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/suspended-carts/complete', [
            'queue_code' => 'Q-99999999-9999',
            'target_station_id' => $this->station->id,
            'payment_method' => 'cash',
            'bank_account_code' => '1102',
        ]);

        // Validation will fail because queue_code doesn't exist in DB
        $response->assertStatus(422);
    }

    // =========================================================================
    // 6. VOID CART TESTS
    // =========================================================================

    public function test_can_void_a_pending_cart(): void
    {
        Sanctum::actingAs($this->user);

        $cart = SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'pending',
        ]);

        SuspendedCartItem::create([
            'suspended_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'unit_price' => 15000,
            'subtotal' => 15000,
        ]);

        $response = $this->putJson('/api/suspended-carts/void/Q-20260521-0001');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Keranjang belanja gantung berhasil di-void.');

        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0001',
            'status' => 'void',
        ]);
    }

    public function test_void_cart_returns_404_if_not_pending(): void
    {
        Sanctum::actingAs($this->user);

        // Create a completed cart that should not be void-able
        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0010',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'completed',
        ]);

        $response = $this->putJson('/api/suspended-carts/void/Q-20260521-0010');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Keranjang belanja gantung tidak ditemukan atau sudah diproses!');
    }

    public function test_void_cart_returns_404_for_unknown_queue_code(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/suspended-carts/void/Q-00000000-0000');

        $response->assertStatus(404);
    }

    // =========================================================================
    // 7. RESET CARTS (BULK VOID) TESTS
    // =========================================================================

    public function test_can_reset_all_pending_carts_via_station_id(): void
    {
        Sanctum::actingAs($this->user);

        // Create multiple pending carts for the station
        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'pending',
        ]);

        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0002',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 2,
            'total_amount' => 30000,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/suspended-carts/reset', [
            'station_id' => $this->station->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Semua antrean belanja gantung stasiun kasir berhasil di-reset.')
            ->assertJsonPath('data.reset_count', 2);

        // All pending carts for this station must now be voided
        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0001',
            'status' => 'void',
        ]);
        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0002',
            'status' => 'void',
        ]);
    }

    public function test_reset_carts_via_active_shift_when_no_station_provided(): void
    {
        Sanctum::actingAs($this->user);

        // Create an open shift for this user on the station
        Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => now(),
            'starting_cash' => 100000.00,
            'expected_cash' => 100000.00,
            'status' => 'open',
        ]);

        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'pending',
        ]);

        // Call reset without passing station_id - it should infer from the active shift
        $response = $this->postJson('/api/suspended-carts/reset');

        $response->assertStatus(200)
            ->assertJsonPath('data.reset_count', 1);

        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0001',
            'status' => 'void',
        ]);
    }

    public function test_reset_carts_returns_400_when_no_station_can_be_determined(): void
    {
        Sanctum::actingAs($this->user);

        // No active shift, no station_id passed
        $response = $this->postJson('/api/suspended-carts/reset');

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Gagal meriset! Stasiun kasir tidak teridentifikasi atau tidak ada shift aktif.');
    }

    public function test_reset_carts_only_affects_pending_status_carts(): void
    {
        Sanctum::actingAs($this->user);

        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0001',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'pending',
        ]);

        // This completed cart should NOT be affected by reset
        SuspendedCart::create([
            'queue_code' => 'Q-20260521-0002',
            'station_id' => $this->station->id,
            'user_id' => $this->user->id,
            'total_items' => 1,
            'total_amount' => 15000,
            'status' => 'completed',
        ]);

        $response = $this->postJson('/api/suspended-carts/reset', [
            'station_id' => $this->station->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.reset_count', 1); // Only 1 pending cart affected

        // Completed cart must remain completed
        $this->assertDatabaseHas('suspended_carts', [
            'queue_code' => 'Q-20260521-0002',
            'status' => 'completed',
        ]);
    }
}
