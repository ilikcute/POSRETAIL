<?php

namespace Tests\Feature\Inventory;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\StockOpnameItem;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockOpnameControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Warehouse $warehouse;

    protected Product $product;

    protected Account $inventoryAccount;

    protected Account $expenseAccount;

    protected Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Setup accounts for stock opname adjustments
        $this->inventoryAccount = Account::create([
            'code' => '1201',
            'name' => 'Aset Persediaan',
            'type' => 'asset',
            'balance' => 1000000.00,
            'is_active' => true,
        ]);

        $this->expenseAccount = Account::create([
            'code' => '5202',
            'name' => 'Beban Selisih Stock',
            'type' => 'expense',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        $this->revenueAccount = Account::create([
            'code' => '4201',
            'name' => 'Pendapatan Lain-lain',
            'type' => 'revenue',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        // Setup master data
        $this->warehouse = Warehouse::factory()->create([
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'cost_price' => 50000.00, // Rp 50.000 cost
            'is_active' => true,
        ]);

        // Base system stock qty = 10
        ProductStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'qty' => 10,
        ]);
    }

    public function test_guest_cannot_access_stock_opname_endpoints(): void
    {
        $this->getJson('/api/stock-opnames')->assertStatus(401);
        $this->postJson('/api/stock-opnames', [])->assertStatus(401);
        $this->getJson('/api/stock-opnames/1')->assertStatus(401);
        $this->putJson('/api/stock-opnames/1', [])->assertStatus(401);
        $this->deleteJson('/api/stock-opnames/1')->assertStatus(401);
    }

    public function test_can_get_paginated_stock_opnames_with_filters(): void
    {
        Sanctum::actingAs($this->user);

        // Create a few stock opnames
        $opname1 = StockOpname::create([
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'SO-20260522-0001',
            'opname_date' => '2026-05-22',
            'status' => 'draft',
            'notes' => 'Testing search query',
            'created_by' => $this->user->id,
        ]);

        $opname2 = StockOpname::create([
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'SO-20260522-0002',
            'opname_date' => '2026-05-22',
            'status' => 'approved',
            'notes' => 'Approved stock opname',
            'created_by' => $this->user->id,
        ]);

        // Get index list
        $response = $this->getJson('/api/stock-opnames');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'data' => ['data', 'current_page', 'total']]);

        $this->assertCount(2, $response->json('data.data'));

        // Search query
        $searchResponse = $this->getJson('/api/stock-opnames?search=Testing');
        $searchResponse->assertStatus(200);
        $this->assertCount(1, $searchResponse->json('data.data'));
        $this->assertEquals('SO-20260522-0001', $searchResponse->json('data.data.0.reference_no'));

        // Status filter
        $statusResponse = $this->getJson('/api/stock-opnames?status=approved');
        $statusResponse->assertStatus(200);
        $this->assertCount(1, $statusResponse->json('data.data'));
        $this->assertEquals('SO-20260522-0002', $statusResponse->json('data.data.0.reference_no'));
    }

    public function test_can_create_draft_stock_opname(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => '2026-05-22',
            'notes' => 'New stock opname draft',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'physical_qty' => 12, // system is 10, physical is 12 (surplus of 2)
                    'notes' => 'Looks good',
                ],
            ],
        ];

        $response = $this->postJson('/api/stock-opnames', $payload);
        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'draft');

        $opnameId = $response->json('data.id');

        $this->assertDatabaseHas('stock_opnames', [
            'id' => $opnameId,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'notes' => 'New stock opname draft',
        ]);

        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opnameId,
            'product_id' => $this->product->id,
            'system_qty' => 10.00,
            'physical_qty' => 12.00,
            'discrepancy' => 2.00,
            'unit_cost' => 50000.00,
            'discrepancy_value' => 100000.00, // 2 * 50k
        ]);
    }

    public function test_can_update_draft_stock_opname_items(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Create a draft opname with 1 item
        $opname = StockOpname::create([
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'SO-20260522-0003',
            'opname_date' => '2026-05-22',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $item1 = StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'system_qty' => 10,
            'physical_qty' => 12,
            'discrepancy' => 2,
            'unit_cost' => 50000.00,
            'discrepancy_value' => 100000.00,
        ]);

        // Create second product for update
        $product2 = Product::factory()->create([
            'cost_price' => 20000.00,
            'is_active' => true,
        ]);

        ProductStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product2->id,
            'qty' => 5,
        ]);

        // 2. Perform update with new items list
        $payload = [
            'notes' => 'Updated notes',
            'items' => [
                [
                    'product_id' => $product2->id,
                    'physical_qty' => 8, // discrepancy: 8 - 5 = 3
                    'notes' => 'Found extra',
                ],
            ],
        ];

        $response = $this->putJson("/api/stock-opnames/{$opname->id}", $payload);
        $response->assertStatus(200);

        // Assert old item was deleted
        $this->assertDatabaseMissing('stock_opname_items', ['id' => $item1->id]);

        // Assert new item exists
        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opname->id,
            'product_id' => $product2->id,
            'system_qty' => 5.00,
            'physical_qty' => 8.00,
            'discrepancy' => 3.00,
            'unit_cost' => 20000.00,
            'discrepancy_value' => 60000.00,
        ]);

        $this->assertEquals('Updated notes', $opname->fresh()->notes);
    }

    public function test_can_approve_stock_opname_surplus_and_updates_stocks_and_journals(): void
    {
        Sanctum::actingAs($this->user);

        // Create draft stock opname
        $opname = StockOpname::create([
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'SO-20260522-0004',
            'opname_date' => '2026-05-22',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'system_qty' => 10,
            'physical_qty' => 12, // discrepancy +2
            'discrepancy' => 2,
            'unit_cost' => 50000.00,
            'discrepancy_value' => 100000.00,
        ]);

        // Approve it
        $response = $this->putJson("/api/stock-opnames/{$opname->id}", [
            'status' => 'approved',
        ]);

        $response->assertStatus(200);

        // 1. Stock Qty in warehouse should update to physical qty (12)
        $productStock = ProductStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(12, $productStock->qty);

        // 2. Journal Entry should be posted for the surplus (+100k)
        // Debit: Aset Persediaan (+100k)
        // Credit: Pendapatan Lain-lain (+100k)
        $this->assertEquals(1100000.00, $this->inventoryAccount->fresh()->balance);
        $this->assertEquals(100000.00, $this->revenueAccount->fresh()->balance);
        $this->assertEquals(0.00, $this->expenseAccount->fresh()->balance);

        $this->assertDatabaseHas('journal_entries', [
            'created_by' => $this->user->id,
            'reference_no' => 'JV-SO-'.str_pad($opname->id, 6, '0', STR_PAD_LEFT),
        ]);
    }

    public function test_can_approve_stock_opname_deficit_and_updates_stocks_and_journals(): void
    {
        Sanctum::actingAs($this->user);

        // Create draft stock opname
        $opname = StockOpname::create([
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'SO-20260522-0005',
            'opname_date' => '2026-05-22',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'system_qty' => 10,
            'physical_qty' => 7, // discrepancy -3
            'discrepancy' => -3,
            'unit_cost' => 50000.00,
            'discrepancy_value' => -150000.00,
        ]);

        // Approve it
        $response = $this->putJson("/api/stock-opnames/{$opname->id}", [
            'status' => 'approved',
        ]);

        $response->assertStatus(200);

        // 1. Stock Qty in warehouse should update to physical qty (7)
        $productStock = ProductStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(7, $productStock->qty);

        // 2. Journal Entry should be posted for the deficit (150k)
        // Debit: Beban Selisih Stock (+150k)
        // Credit: Aset Persediaan (-150k)
        $this->assertEquals(850000.00, $this->inventoryAccount->fresh()->balance);
        $this->assertEquals(0.00, $this->revenueAccount->fresh()->balance);
        $this->assertEquals(150000.00, $this->expenseAccount->fresh()->balance);
    }

    public function test_cannot_update_non_draft_stock_opname(): void
    {
        Sanctum::actingAs($this->user);

        $opname = StockOpname::create([
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'SO-20260522-0006',
            'opname_date' => '2026-05-22',
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/stock-opnames/{$opname->id}", [
            'notes' => 'Attempting update',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonFragment(['status' => ['Cannot update stock opname that is already approved.']]);
    }

    public function test_cannot_delete_non_draft_stock_opname(): void
    {
        Sanctum::actingAs($this->user);

        $opname = StockOpname::create([
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'SO-20260522-0007',
            'opname_date' => '2026-05-22',
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/stock-opnames/{$opname->id}");

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonFragment(['status' => ['Only draft stock opnames can be deleted.']]);
    }

    public function test_can_delete_draft_stock_opname(): void
    {
        Sanctum::actingAs($this->user);

        $opname = StockOpname::create([
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'SO-20260522-0008',
            'opname_date' => '2026-05-22',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $item = StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'system_qty' => 10,
            'physical_qty' => 12,
            'discrepancy' => 2,
            'unit_cost' => 50000.00,
            'discrepancy_value' => 100000.00,
        ]);

        $response = $this->deleteJson("/api/stock-opnames/{$opname->id}");
        $response->assertStatus(200);

        // Header and item must be gone
        $this->assertSoftDeleted('stock_opnames', ['id' => $opname->id]);
        $this->assertDatabaseMissing('stock_opname_items', ['id' => $item->id]);
    }
}
