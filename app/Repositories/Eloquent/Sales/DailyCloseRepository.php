<?php

namespace App\Repositories\Eloquent\Sales;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Sales\DailyClose;
use App\Models\Sales\Sale;
use App\Models\Purchase\Purchase;
use App\Models\Sales\Shift;
use App\Repositories\Contracts\Sales\DailyCloseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DailyCloseRepository extends BaseRepository implements DailyCloseRepositoryInterface
{
    public function __construct(DailyClose $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $closeDate = $attributes['close_date'];

            // Total Penjualan Sukses Hari Ini
            $totalSales = Sale::whereDate('created_at', $closeDate)
                ->where('status', 'completed')
                ->sum('grand_total');

            // Total Penjualan Cash vs Non-Cash
            $totalCashSales = Sale::whereDate('created_at', $closeDate)
                ->where('status', 'completed')
                ->where('payment_method', 'cash')
                ->sum('grand_total');

            $totalNonCashSales = $totalSales - $totalCashSales;

            // Total Pembelian Sukses Hari Ini
            $totalPurchases = Purchase::whereDate('created_at', $closeDate)
                ->where('status', 'received')
                ->sum('grand_total');

            // Selisih Uang Laci Kasir dari Shift yang tutup hari ini (Cash, QRIS, Card)
            $totalShiftDifference = Shift::whereDate('end_time', $closeDate)
                ->sum(DB::raw('difference_cash + difference_qris + difference_card'));

            $attributes['total_sales'] = $totalSales;
            $attributes['total_purchases'] = $totalPurchases;
            $attributes['total_cash_sales'] = $totalCashSales;
            $attributes['total_non_cash_sales'] = $totalNonCashSales;
            $attributes['total_shift_difference'] = $totalShiftDifference;
            $attributes['closed_by'] = auth()->id() ?? 1;
            $attributes['status'] = 'completed';

            return parent::create($attributes);
        });
    }
}
