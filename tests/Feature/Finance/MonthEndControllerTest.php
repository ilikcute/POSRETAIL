<?php

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Finance\MonthEnd;
use App\Models\Master\Product;
use App\Models\Master\Station;
use App\Models\Master\Store;
use App\Models\Master\Supplier;
use App\Models\Master\Warehouse;
use App\Models\Purchase\Purchase;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Sales\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonthEndControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store1;

    protected Store $store2;

    protected Warehouse $warehouse;

    protected Station $station;

    protected Product $product;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create stores and dependencies
        $this->store1 = Store::factory()->create(['name' => 'Toko Cabang A']);
        $this->store2 = Store::factory()->create(['name' => 'Toko Cabang B']);
        $this->warehouse = Warehouse::factory()->create();
        $this->station = Station::factory()->create();
        $this->product = Product::factory()->create();
        $this->supplier = Supplier::factory()->create();
    }

    public function test_guest_cannot_access_month_ends_api(): void
    {
        $this->getJson('/api/month-ends')->assertStatus(401);
        $this->getJson('/api/month-ends/preview?store_id=1&month=5&year=2026')->assertStatus(401);
        $this->postJson('/api/month-ends', [])->assertStatus(401);
        $this->getJson('/api/month-ends/1')->assertStatus(401);
        $this->deleteJson('/api/month-ends/1')->assertStatus(401);
    }

    public function test_can_preview_month_end_with_correct_store_scoped_calculations(): void
    {
        Sanctum::actingAs($this->user);

        $month = 5;
        $year = 2026;

        // Sales for Store 1 (within EOM period)
        $sale1 = Sale::create([
            'store_id' => $this->store1->id,
            'station_id' => $this->station->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_no' => 'INV-001',
            'status' => 'completed',
            'grand_total' => 150000.00,
            'created_by' => $this->user->id,
            'created_at' => '2026-05-15 10:00:00',
        ]);
        SaleItem::create([
            'sale_id' => $sale1->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'unit_price' => 75000.00,
            'cost_price' => 50000.00,
            'subtotal' => 150000.00,
        ]);

        // Sale for Store 2 (should be excluded from Store 1's preview)
        $sale2 = Sale::create([
            'store_id' => $this->store2->id,
            'station_id' => $this->station->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_no' => 'INV-002',
            'status' => 'completed',
            'grand_total' => 300000.00,
            'created_by' => $this->user->id,
            'created_at' => '2026-05-16 11:00:00',
        ]);
        SaleItem::create([
            'sale_id' => $sale2->id,
            'product_id' => $this->product->id,
            'qty' => 3,
            'unit_price' => 100000.00,
            'cost_price' => 70000.00,
            'subtotal' => 300000.00,
        ]);

        // Purchases for Store 1
        Purchase::create([
            'store_id' => $this->store1->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'PURCH-001',
            'type' => 'purchase',
            'status' => 'received',
            'payment_status' => 'paid',
            'grand_total' => 80000.00,
            'created_by' => $this->user->id,
            'created_at' => '2026-05-10 09:00:00',
        ]);

        // Purchase for Store 2 (should be excluded)
        Purchase::create([
            'store_id' => $this->store2->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_no' => 'PURCH-002',
            'type' => 'purchase',
            'status' => 'received',
            'payment_status' => 'paid',
            'grand_total' => 120000.00,
            'created_by' => $this->user->id,
            'created_at' => '2026-05-12 09:00:00',
        ]);

        $response = $this->getJson("/api/month-ends/preview?store_id={$this->store1->id}&month={$month}&year={$year}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.store.id', $this->store1->id)
            ->assertJsonPath('data.month', $month)
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.totals.total_sales', 150000)
            ->assertJsonPath('data.totals.total_cost_of_goods_sold', 100000) // 2 * 50000.00
            ->assertJsonPath('data.totals.total_purchases', 80000)
            ->assertJsonPath('data.totals.gross_profit', 50000); // 150000 - 100000
    }

    public function test_cannot_preview_or_close_future_month(): void
    {
        Sanctum::actingAs($this->user);

        // Get a future date
        $futureDate = now()->addMonth();
        $month = (int) $futureDate->format('m');
        $year = (int) $futureDate->format('Y');

        // Preview should return is_future = true and can_close = false
        $responsePreview = $this->getJson("/api/month-ends/preview?store_id={$this->store1->id}&month={$month}&year={$year}");
        $responsePreview->assertStatus(200)
            ->assertJsonPath('data.is_future', true)
            ->assertJsonPath('data.can_close', false);

        // Store request should return 422
        $responseStore = $this->postJson('/api/month-ends', [
            'store_id' => $this->store1->id,
            'month' => $month,
            'year' => $year,
        ]);

        $responseStore->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_cannot_preview_or_close_duplicate_period(): void
    {
        Sanctum::actingAs($this->user);

        $month = 4;
        $year = 2026;

        // Create an existing closing record for this store
        MonthEnd::create([
            'store_id' => $this->store1->id,
            'month' => $month,
            'year' => $year,
            'total_sales' => 50000,
            'total_cost_of_goods_sold' => 30000,
            'total_purchases' => 40000,
            'gross_profit' => 20000,
            'closed_by' => $this->user->id,
            'closed_at' => now(),
        ]);

        // Preview should flag already_closed = true
        $responsePreview = $this->getJson("/api/month-ends/preview?store_id={$this->store1->id}&month={$month}&year={$year}");
        $responsePreview->assertStatus(200)
            ->assertJsonPath('data.already_closed', true)
            ->assertJsonPath('data.can_close', false);

        // Store duplicate should fail
        $responseStore = $this->postJson('/api/month-ends', [
            'store_id' => $this->store1->id,
            'month' => $month,
            'year' => $year,
        ]);

        $responseStore->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_cannot_preview_or_close_if_open_shifts_exist(): void
    {
        Sanctum::actingAs($this->user);

        $month = 5;
        $year = 2026;

        // Create an open shift for the store
        Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => '2026-05-15 08:00:00',
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        // Preview should have open_shift_count = 1
        $responsePreview = $this->getJson("/api/month-ends/preview?store_id={$this->store1->id}&month={$month}&year={$year}");
        $responsePreview->assertStatus(200)
            ->assertJsonPath('data.open_shift_count', 1)
            ->assertJsonPath('data.can_close', false);

        // Store should fail
        $responseStore = $this->postJson('/api/month-ends', [
            'store_id' => $this->store1->id,
            'month' => $month,
            'year' => $year,
        ]);

        $responseStore->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_can_successfully_close_month_end(): void
    {
        Sanctum::actingAs($this->user);

        $month = 5;
        $year = 2026;

        // Create closed shifts so we don't block
        Shift::create([
            'user_id' => $this->user->id,
            'station_id' => $this->station->id,
            'start_time' => '2026-05-15 08:00:00',
            'end_time' => '2026-05-15 17:00:00',
            'starting_cash' => 100000,
            'status' => 'closed',
        ]);

        // Create a completed sale
        $sale = Sale::create([
            'store_id' => $this->store1->id,
            'station_id' => $this->station->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_no' => 'INV-100',
            'status' => 'completed',
            'grand_total' => 200000.00,
            'created_by' => $this->user->id,
            'created_at' => '2026-05-15 10:00:00',
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'unit_price' => 100000.00,
            'cost_price' => 60000.00,
            'subtotal' => 200000.00,
        ]);

        $payload = [
            'store_id' => $this->store1->id,
            'month' => $month,
            'year' => $year,
            'notes' => 'EOM Close Toko Cabang A Mei 2026',
        ];

        $response = $this->postJson('/api/month-ends', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.store_id', $this->store1->id)
            ->assertJsonPath('data.month', $month)
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.total_sales', '200000.00')
            ->assertJsonPath('data.total_cost_of_goods_sold', '120000.00')
            ->assertJsonPath('data.total_purchases', '0.00')
            ->assertJsonPath('data.gross_profit', '80000.00')
            ->assertJsonPath('data.notes', 'EOM Close Toko Cabang A Mei 2026');

        $this->assertDatabaseHas('month_ends', [
            'store_id' => $this->store1->id,
            'month' => $month,
            'year' => $year,
        ]);
    }

    public function test_can_get_paginated_month_ends_list_and_details(): void
    {
        Sanctum::actingAs($this->user);

        // Create 2 records
        $me1 = MonthEnd::create([
            'store_id' => $this->store1->id,
            'month' => 4,
            'year' => 2026,
            'total_sales' => 100000,
            'total_cost_of_goods_sold' => 60000,
            'total_purchases' => 50000,
            'gross_profit' => 40000,
            'closed_by' => $this->user->id,
            'closed_at' => now(),
            'notes' => 'April Close',
        ]);

        $me2 = MonthEnd::create([
            'store_id' => $this->store2->id,
            'month' => 5,
            'year' => 2026,
            'total_sales' => 200000,
            'total_cost_of_goods_sold' => 120000,
            'total_purchases' => 100000,
            'gross_profit' => 80000,
            'closed_by' => $this->user->id,
            'closed_at' => now(),
            'notes' => 'Mei Close',
        ]);

        // Test list with no filters
        $response = $this->getJson('/api/month-ends');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['data', 'current_page', 'total']]);
        $this->assertCount(2, $response->json('data.data'));

        // Test list filter by store_id
        $responseFilterStore = $this->getJson("/api/month-ends?store_id={$this->store1->id}");
        $responseFilterStore->assertStatus(200);
        $this->assertCount(1, $responseFilterStore->json('data.data'));
        $this->assertEquals($me1->id, $responseFilterStore->json('data.data.0.id'));

        // Test list filter by search
        $responseSearch = $this->getJson('/api/month-ends?search=Mei');
        $responseSearch->assertStatus(200);
        $this->assertCount(1, $responseSearch->json('data.data'));
        $this->assertEquals($me2->id, $responseSearch->json('data.data.0.id'));

        // Test show details
        $responseShow = $this->getJson("/api/month-ends/{$me1->id}");
        $responseShow->assertStatus(200)
            ->assertJsonPath('data.notes', 'April Close');
    }

    public function test_can_delete_month_end(): void
    {
        Sanctum::actingAs($this->user);

        $me = MonthEnd::create([
            'store_id' => $this->store1->id,
            'month' => 5,
            'year' => 2026,
            'total_sales' => 100000,
            'total_cost_of_goods_sold' => 60000,
            'total_purchases' => 50000,
            'gross_profit' => 40000,
            'closed_by' => $this->user->id,
            'closed_at' => now(),
            'notes' => 'April Close',
        ]);

        $this->assertDatabaseHas('month_ends', ['id' => $me->id]);

        $response = $this->deleteJson("/api/month-ends/{$me->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('month_ends', ['id' => $me->id]);
    }
}
