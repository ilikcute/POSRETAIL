<?php

namespace App\Repositories\Eloquent\Finance;

use App\Models\Finance\MonthEnd;
use App\Models\Purchase\Purchase;
use App\Models\Sales\Sale;
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

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $month = $attributes['month'];
            $year = $attributes['year'];

            // Total Penjualan Bulanan
            $totalSales = Sale::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->where('status', 'completed')
                ->sum('grand_total');

            // Total HPP (Cost of Goods Sold)
            // Mengambil sum dari qty * cost_price di table sale_items
            $totalCOGS = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->whereMonth('sales.created_at', $month)
                ->whereYear('sales.created_at', $year)
                ->where('sales.status', 'completed')
                ->sum(DB::raw('sale_items.qty * sale_items.cost_price'));

            // Total Pembelian Bulanan
            $totalPurchases = Purchase::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->where('status', 'received')
                ->sum('grand_total');

            // Laba Kotor (Gross Profit)
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
