<?php

namespace Tests\Feature\Sales;

use App\Models\Auth\User;
use App\Models\Master\Station;
use App\Models\Master\Store;
use App\Models\Master\Supplier;
use App\Models\Master\Warehouse;
use App\Models\Purchase\Purchase;
use App\Models\Sales\DailyClose;
use App\Models\Sales\Sale;
use App\Models\Sales\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_daily_close_api(): void
    {
        $this->getJson('/api/daily-closes')->assertStatus(401);
        $this->postJson('/api/daily-closes', [])->assertStatus(401);
    }

    public function test_user_can_preview_daily_close_summary(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $station = Station::factory()->create();
        $closeDate = '2026-05-20';
        $shift = Shift::query()->create([
            'user_id' => $user->id,
            'station_id' => $station->id,
            'start_time' => "{$closeDate} 08:00:00",
            'end_time' => "{$closeDate} 16:00:00",
            'starting_cash' => 100000,
            'difference_cash' => 5000,
            'difference_qris' => 0,
            'difference_card' => -2000,
            'status' => 'closed',
        ]);

        Sale::factory()->create([
            'store_id' => $store->id,
            'station_id' => $station->id,
            'shift_id' => $shift->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'payment_method' => 'cash',
            'grand_total' => 100000,
            'discount_amount' => 10000,
            'tax_amount' => 11000,
            'status' => 'completed',
            'created_at' => "{$closeDate} 10:00:00",
        ]);

        Sale::factory()->create([
            'store_id' => $store->id,
            'station_id' => $station->id,
            'shift_id' => $shift->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'payment_method' => 'qris',
            'grand_total' => 250000,
            'status' => 'completed',
            'created_at' => "{$closeDate} 11:00:00",
        ]);

        Purchase::factory()->create([
            'store_id' => $store->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'status' => 'received',
            'grand_total' => 75000,
            'created_at' => "{$closeDate} 12:00:00",
        ]);

        $response = $this->getJson("/api/daily-closes/preview?store_id={$store->id}&close_date={$closeDate}");

        $response->assertOk()
            ->assertJsonPath('data.can_close', true)
            ->assertJsonPath('data.sales_count', 2)
            ->assertJsonPath('data.totals.total_sales', 350000)
            ->assertJsonPath('data.totals.total_cash_sales', 100000)
            ->assertJsonPath('data.totals.total_non_cash_sales', 250000)
            ->assertJsonPath('data.totals.total_purchases', 75000)
            ->assertJsonPath('data.totals.total_shift_difference', 3000);
    }

    public function test_user_can_submit_daily_close_when_no_open_shift(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $station = Station::factory()->create();
        $closeDate = '2026-05-21';
        $shift = Shift::query()->create([
            'user_id' => $user->id,
            'station_id' => $station->id,
            'start_time' => "{$closeDate} 08:00:00",
            'end_time' => "{$closeDate} 17:00:00",
            'difference_cash' => 0,
            'difference_qris' => 0,
            'difference_card' => 0,
            'status' => 'closed',
        ]);

        Sale::factory()->create([
            'store_id' => $store->id,
            'station_id' => $station->id,
            'shift_id' => $shift->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'payment_method' => 'cash',
            'grand_total' => 125000,
            'status' => 'completed',
            'created_at' => "{$closeDate} 09:00:00",
        ]);

        $response = $this->postJson('/api/daily-closes', [
            'store_id' => $store->id,
            'close_date' => $closeDate,
            'notes' => 'EOD test',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.store_id', $store->id)
            ->assertJsonPath('data.total_sales', '125000.00')
            ->assertJsonPath('data.closed_by.id', $user->id)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('daily_closes', [
            'store_id' => $store->id,
            'close_date' => $closeDate,
            'closed_by' => $user->id,
        ]);
    }

    public function test_daily_close_is_rejected_when_shift_is_still_open(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $store = Store::factory()->create();
        $station = Station::factory()->create();
        $closeDate = '2026-05-22';

        Shift::query()->create([
            'user_id' => $user->id,
            'station_id' => $station->id,
            'start_time' => "{$closeDate} 08:00:00",
            'status' => 'open',
        ]);

        $response = $this->postJson('/api/daily-closes', [
            'store_id' => $store->id,
            'close_date' => $closeDate,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['errors' => ['daily_close', 'close_date']]);

        $this->assertSame(0, DailyClose::query()->count());
    }

    public function test_daily_close_date_cannot_be_closed_twice(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $store = Store::factory()->create();
        $closeDate = '2026-05-23';

        DailyClose::query()->create([
            'store_id' => $store->id,
            'close_date' => $closeDate,
            'closed_by' => $user->id,
            'status' => 'completed',
        ]);

        $this->postJson('/api/daily-closes', [
            'store_id' => $store->id,
            'close_date' => $closeDate,
        ])->assertStatus(422);
    }

    public function test_daily_close_delete_is_blocked_to_preserve_audit_trail(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $close = DailyClose::query()->create([
            'store_id' => Store::factory()->create()->id,
            'close_date' => '2026-05-24',
            'closed_by' => $user->id,
            'status' => 'completed',
        ]);

        $this->deleteJson('/api/daily-closes/'.$close->id)->assertStatus(409);
        $this->assertModelExists($close);
    }
}
