<?php

namespace App\Repositories\Eloquent\Sales;

use App\Models\Finance\CashTransaction;
use App\Models\Sales\Sale;
use App\Models\Sales\Shift;
use App\Repositories\Contracts\Sales\ShiftRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;

class ShiftRepository extends BaseRepository implements ShiftRepositoryInterface
{
    public function __construct(Shift $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        $attributes['user_id'] = auth()->id() ?? 1;
        $attributes['start_time'] = now();
        $attributes['status'] = 'open';
        $attributes['expected_cash'] = $attributes['starting_cash'];

        return parent::create($attributes);
    }

    public function closeShift(int $id, array $data)
    {
        $shift = $this->findOrFail($id);

        if ($shift->status === 'closed') {
            throw new \Exception('Shift is already closed.');
        }

        // 1. Total Penjualan & Diskon
        $totalSales = Sale::where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->sum('grand_total');

        $totalDiscount = Sale::where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->sum('discount_amount');

        // 2. Ekspektasi Cash
        $cashSales = Sale::where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->sum('grand_total');

        // Uang Masuk Lain-lain (misal penjualan kardus bekas)
        $cashInflows = CashTransaction::where('shift_id', $shift->id)
            ->where('type', 'in')
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Uang Keluar Lain-lain (misal beban bayar listrik/atk)
        $cashOutflows = CashTransaction::where('shift_id', $shift->id)
            ->where('type', 'out')
            ->where('payment_method', 'cash')
            ->sum('amount');

        $expectedCash = $shift->starting_cash + $cashSales + $cashInflows - $cashOutflows;
        $actualCash = $data['actual_cash'];
        $differenceCash = $actualCash - $expectedCash;

        // 3. Ekspektasi QRIS
        $expectedQris = Sale::where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->where('payment_method', 'qris')
            ->sum('grand_total');

        $actualQris = $data['actual_qris'];
        $differenceQris = $actualQris - $expectedQris;

        // 4. Ekspektasi Card
        $expectedCard = Sale::where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->where('payment_method', 'card')
                    ->orWhere('payment_method', 'debit')
                    ->orWhere('payment_method', 'credit');
            })
            ->sum('grand_total');

        $actualCard = $data['actual_card'];
        $differenceCard = $actualCard - $expectedCard;

        return parent::update($id, [
            'end_time' => now(),
            'total_sales' => $totalSales,
            'total_discount' => $totalDiscount,
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'difference_cash' => $differenceCash,
            'expected_qris' => $expectedQris,
            'actual_qris' => $actualQris,
            'difference_qris' => $differenceQris,
            'expected_card' => $expectedCard,
            'actual_card' => $actualCard,
            'difference_card' => $differenceCard,
            'status' => 'closed',
            'notes' => $data['notes'] ?? $shift->notes,
        ]);
    }
}
