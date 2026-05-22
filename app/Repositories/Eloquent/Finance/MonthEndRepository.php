<?php

namespace App\Repositories\Eloquent\Finance;

use App\Exceptions\MonthEndException;
use App\Models\Finance\MonthEnd;
use App\Models\Master\Store;
use App\Models\Purchase\Purchase;
use App\Models\Sales\Sale;
use App\Models\Sales\Shift;
use App\Repositories\Contracts\Finance\MonthEndRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MonthEndRepository extends BaseRepository implements MonthEndRepositoryInterface
{
    public function __construct(MonthEnd $model)
    {
        parent::__construct($model);
    }

    /**
     * Generate preview details for Month End closing.
     */
    public function preview(array $attributes): array
    {
        $storeId = (int) $attributes['store_id'];
        $month = (int) $attributes['month'];
        $year = (int) $attributes['year'];

        $store = Store::findOrFail($storeId);

        // 1. Validasi Periode di Masa Mendatang
        $currentDate = now();
        $currentMonth = (int) $currentDate->format('m');
        $currentYear = (int) $currentDate->format('Y');
        $isFuture = $year > $currentYear || ($year == $currentYear && $month > $currentMonth);

        // 2. Validasi Duplikasi Closing
        $alreadyClosed = MonthEnd::where('store_id', $storeId)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();

        // 3. Validasi Shift Kasir yang Belum Ditutup
        $openShiftCount = Shift::where('status', 'open')
            ->whereMonth('start_time', $month)
            ->whereYear('start_time', $year)
            ->where(function ($query) use ($storeId) {
                $query->whereDoesntHave('sales')
                    ->orWhereHas('sales', fn ($salesQuery) => $salesQuery->where('store_id', $storeId));
            })
            ->count();

        // 4. Kalkulasi Stats Scoped ke store_id
        $totalSales = (float) Sale::where('store_id', $storeId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->where('status', 'completed')
            ->sum('grand_total');

        $totalCOGS = (float) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.store_id', $storeId)
            ->whereMonth('sales.created_at', $month)
            ->whereYear('sales.created_at', $year)
            ->where('sales.status', 'completed')
            ->sum(DB::raw('sale_items.qty * sale_items.cost_price'));

        $totalPurchases = (float) Purchase::where('store_id', $storeId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->where('status', 'received')
            ->sum('grand_total');

        $grossProfit = $totalSales - $totalCOGS;

        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
            ],
            'month' => $month,
            'year' => $year,
            'already_closed' => $alreadyClosed,
            'is_future' => $isFuture,
            'open_shift_count' => $openShiftCount,
            'totals' => [
                'total_sales' => round($totalSales, 2),
                'total_cost_of_goods_sold' => round($totalCOGS, 2),
                'total_purchases' => round($totalPurchases, 2),
                'gross_profit' => round($grossProfit, 2),
            ],
            'can_close' => ! $alreadyClosed && ! $isFuture && $openShiftCount === 0,
        ];
    }

    /**
     * Create a new Month End record.
     */
    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $storeId = (int) $attributes['store_id'];
            $month = (int) $attributes['month'];
            $year = (int) $attributes['year'];

            // Lock store to prevent concurrent EOM runs for same store
            /** @var Store $store */
            $store = Store::whereKey($storeId)->lockForUpdate()->firstOrFail();

            // 1. Validasi Periode di Masa Mendatang
            $currentDate = now();
            $currentMonth = (int) $currentDate->format('m');
            $currentYear = (int) $currentDate->format('Y');
            if ($year > $currentYear || ($year == $currentYear && $month > $currentMonth)) {
                throw new MonthEndException('Tidak dapat memproses penutupan bulan untuk periode di masa mendatang.');
            }

            // 2. Validasi Duplikasi Closing dengan Row Lock
            if (MonthEnd::where('store_id', $storeId)->where('month', $month)->where('year', $year)->lockForUpdate()->exists()) {
                throw new MonthEndException("Penutupan bulan (Month End) untuk periode bulan {$month} tahun {$year} sudah pernah diproses.");
            }

            // 3. Validasi Shift Kasir yang Belum Ditutup
            $openShiftCount = Shift::where('status', 'open')
                ->whereMonth('start_time', $month)
                ->whereYear('start_time', $year)
                ->where(function ($query) use ($storeId) {
                    $query->whereDoesntHave('sales')
                        ->orWhereHas('sales', fn ($salesQuery) => $salesQuery->where('store_id', $storeId));
                })
                ->count();

            if ($openShiftCount > 0) {
                throw new MonthEndException("Month End ditolak karena masih ada {$openShiftCount} shift kasir yang belum ditutup pada periode ini.");
            }

            // 4. Kalkulasi Stats Scoped ke store_id
            $totalSales = (float) Sale::where('store_id', $storeId)
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->where('status', 'completed')
                ->sum('grand_total');

            $totalCOGS = (float) DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->where('sales.store_id', $storeId)
                ->whereMonth('sales.created_at', $month)
                ->whereYear('sales.created_at', $year)
                ->where('sales.status', 'completed')
                ->sum(DB::raw('sale_items.qty * sale_items.cost_price'));

            $totalPurchases = (float) Purchase::where('store_id', $storeId)
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->where('status', 'received')
                ->sum('grand_total');

            $grossProfit = $totalSales - $totalCOGS;

            $attributes['total_sales'] = $totalSales;
            $attributes['total_cost_of_goods_sold'] = $totalCOGS;
            $attributes['total_purchases'] = $totalPurchases;
            $attributes['gross_profit'] = $grossProfit;
            $attributes['closed_by'] = auth()->id() ?? 1;
            $attributes['closed_at'] = now();

            return parent::create($attributes);
        });
    }
}
