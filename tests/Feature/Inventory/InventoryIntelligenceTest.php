<?php

namespace Tests\Feature\Inventory;

use App\Models\Auth\User;
use App\Models\Inventory\ProductStock;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Models\Sales\Promotion;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_inventory_intelligence_routes(): void
    {
        $this->getJson('/api/intelligence/stock-alerts')->assertStatus(401);
        $this->getJson('/api/intelligence/best-sellers')->assertStatus(401);
        $this->getJson('/api/intelligence/promo-performance')->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_stock_alerts(): void
    {
        Sanctum::actingAs($this->user);

        $warehouse = Warehouse::factory()->create();

        $lowStockProduct = Product::factory()->create([
            'reorder_point' => 10,
            'safety_stock' => 5,
        ]);

        $overStockProduct = Product::factory()->create([
            'reorder_point' => 10,
            'safety_stock' => 5,
        ]);

        ProductStock::create([
            'product_id' => $lowStockProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 3,
            'min_qty' => 0,
        ]);

        ProductStock::create([
            'product_id' => $overStockProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 300,
            'min_qty' => 0,
        ]);

        $response = $this->getJson('/api/intelligence/stock-alerts?warehouse_id=' . $warehouse->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_low_stock_items', 1)
            ->assertJsonPath('data.summary.total_over_stock_items', 1)
            ->assertJsonPath('data.alerts.under_stocked.0.code', $lowStockProduct->code)
            ->assertJsonPath('data.alerts.over_stocked.0.code', $overStockProduct->code);
    }

    public function test_authenticated_user_can_fetch_best_sellers_with_valid_period(): void
    {
        Sanctum::actingAs($this->user);

        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $sale = Sale::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'completed',
            'discount_amount' => 50,
            'grand_total' => 450,
            'created_at' => now()->subDays(2),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productA->id,
            'qty' => 5,
            'unit_price' => 100,
            'cost_price' => 60,
            'discount' => 0,
            'tax' => 0,
            'subtotal' => 500,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productB->id,
            'qty' => 3,
            'unit_price' => 150,
            'cost_price' => 100,
            'discount' => 0,
            'tax' => 0,
            'subtotal' => 450,
        ]);

        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->getJson('/api/intelligence/best-sellers?start_date=' . $startDate . '&end_date=' . $endDate . '&limit=2');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_items_sold', 8)
            ->assertJsonPath('data.best_sellers.0.product_id', $productA->id)
            ->assertJsonPath('data.best_sellers.1.product_id', $productB->id);
    }

    public function test_best_sellers_returns_error_when_date_range_is_invalid(): void
    {
        Sanctum::actingAs($this->user);

        $startDate = now()->toDateString();
        $endDate = now()->subDays(7)->toDateString();

        $response = $this->getJson('/api/intelligence/best-sellers?start_date=' . $startDate . '&end_date=' . $endDate . '&limit=2');

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Tanggal mulai harus lebih awal atau sama dengan tanggal akhir.');
    }

    public function test_best_sellers_returns_error_when_limit_is_invalid(): void
    {
        Sanctum::actingAs($this->user);

        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->getJson('/api/intelligence/best-sellers?start_date=' . $startDate . '&end_date=' . $endDate . '&limit=0');

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Limit harus minimal 1.');
    }

    public function test_authenticated_user_can_fetch_promo_performance(): void
    {
        Sanctum::actingAs($this->user);

        $promotion = Promotion::factory()->create();
        $product = Product::factory()->create();

        $sale = Sale::factory()->create([
            'created_by' => $this->user->id,
            'promotion_id' => $promotion->id,
            'status' => 'completed',
            'discount_amount' => 50,
            'grand_total' => 450,
            'created_at' => now()->subDays(1),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 4,
            'unit_price' => 125,
            'cost_price' => 80,
            'discount' => 0,
            'tax' => 0,
            'subtotal' => 500,
        ]);

        $response = $this->getJson('/api/intelligence/promo-performance');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_promotions_analyzed', 1)
            ->assertJsonPath('data.promotions_performance.0.promo_id', $promotion->id)
            ->assertJsonPath('data.promotions_performance.0.top_sold_products.0.name', $product->name);
    }
}
