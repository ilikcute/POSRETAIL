<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\DailyCloseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\PreviewDailyCloseRequest;
use App\Http\Requests\Sales\StoreDailyCloseRequest;
use App\Http\Requests\Sales\UpdateDailyCloseRequest;
use App\Models\Master\Store;
use App\Models\Purchase\Purchase;
use App\Models\Sales\DailyClose;
use App\Models\Sales\Sale;
use App\Models\Sales\Shift;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyCloseController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $closes = DailyClose::query()
            ->with(['store:id,name', 'closedBy:id,name,email'])
            ->latest('close_date')
            ->limit(180)
            ->get();

        return $this->successResponse($closes, 'Daily closes retrieved successfully');
    }

    public function preview(PreviewDailyCloseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $store = Store::query()
            ->select(['id', 'name'])
            ->findOrFail((int) $validated['store_id']);

        $summary = $this->buildSummary($store, $validated['close_date']);

        return $this->successResponse($summary, 'Daily close preview generated successfully');
    }

    /**
     * Melakukan EOD. Semua nominal dihitung ulang di backend dalam transaksi.
     */
    public function store(StoreDailyCloseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $closeDate = $validated['close_date'];
        $storeId = (int) $validated['store_id'];

        try {
            $close = DB::transaction(function () use ($validated, $closeDate, $storeId): DailyClose {
                /** @var Store $store */
                $store = Store::query()
                    ->whereKey($storeId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (DailyClose::query()->whereDate('close_date', $closeDate)->lockForUpdate()->exists()) {
                    throw new DailyCloseException(
                        'Daily Close untuk tanggal ini sudah pernah diproses.',
                        ['close_date' => $closeDate]
                    );
                }

                $summary = $this->buildSummary($store, $closeDate);

                if ($summary['open_shift_count'] > 0) {
                    throw new DailyCloseException(
                        'Daily Close ditolak karena masih ada shift kasir yang belum ditutup.',
                        [
                            'close_date' => $closeDate,
                            'open_shift_count' => $summary['open_shift_count'],
                        ]
                    );
                }

                return DailyClose::query()
                    ->create([
                        'store_id' => $store->id,
                        'close_date' => $closeDate,
                        'total_sales' => $summary['totals']['total_sales'],
                        'total_purchases' => $summary['totals']['total_purchases'],
                        'total_cash_sales' => $summary['totals']['total_cash_sales'],
                        'total_non_cash_sales' => $summary['totals']['total_non_cash_sales'],
                        'total_shift_difference' => $summary['totals']['total_shift_difference'],
                        'closed_by' => auth()->id(),
                        'status' => 'completed',
                        'notes' => $validated['notes'] ?? null,
                    ])
                    ->load(['store:id,name', 'closedBy:id,name,email']);
            });

            return $this->successResponse($close, 'Daily close completed successfully (EOD Done)', 201);
        } catch (DailyCloseException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'daily_close' => $e->contextData(),
                'close_date' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            Log::error('DailyCloseController::store failed', [
                'store_id' => $storeId,
                'close_date' => $closeDate,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Terjadi kesalahan internal saat memproses Daily Close.', 500);
        }
    }

    public function show(int $daily_close): JsonResponse
    {
        $close = DailyClose::query()
            ->with(['store:id,name', 'closedBy:id,name,email'])
            ->findOrFail($daily_close);

        return $this->successResponse($close, 'Daily close details retrieved successfully');
    }

    public function update(UpdateDailyCloseRequest $request, int $daily_close): JsonResponse
    {
        $close = DailyClose::query()->findOrFail($daily_close);
        $validated = $request->validated();

        $close->forceFill([
            'status' => $validated['status'] ?? $close->status,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $close->notes,
        ])->save();

        $close->load(['store:id,name', 'closedBy:id,name,email']);

        return $this->successResponse($close, 'Daily close updated successfully');
    }

    /**
     * Daily Close adalah audit trail EOD, jadi delete diblok.
     */
    public function destroy(int $daily_close): JsonResponse
    {
        return $this->errorResponse(
            'Daily Close tidak dapat dihapus karena merupakan audit trail EOD.',
            409
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(Store $store, string $closeDate): array
    {
        $salesBase = Sale::query()
            ->where('store_id', $store->id)
            ->whereDate('created_at', $closeDate)
            ->where('status', 'completed');

        $totalSales = (float) (clone $salesBase)->sum('grand_total');
        $totalCashSales = (float) (clone $salesBase)
            ->where('payment_method', 'cash')
            ->sum('grand_total');
        $totalNonCashSales = $totalSales - $totalCashSales;

        $salesCount = (clone $salesBase)->count();
        $totalDiscount = (float) (clone $salesBase)->sum('discount_amount');
        $totalTax = (float) (clone $salesBase)->sum('tax_amount');

        $totalPurchases = (float) Purchase::query()
            ->where('store_id', $store->id)
            ->whereDate('created_at', $closeDate)
            ->whereIn('status', ['received', 'completed'])
            ->sum('grand_total');

        $totalShiftDifference = (float) Shift::query()
            ->whereDate('end_time', $closeDate)
            ->where('status', 'closed')
            ->whereHas('sales', fn ($query) => $query->where('store_id', $store->id))
            ->sum(DB::raw('difference_cash + difference_qris + difference_card'));

        $closedShiftCount = Shift::query()
            ->whereDate('end_time', $closeDate)
            ->where('status', 'closed')
            ->whereHas('sales', fn ($query) => $query->where('store_id', $store->id))
            ->count();

        $openShiftCount = Shift::query()
            ->where('status', 'open')
            ->whereDate('start_time', '<=', $closeDate)
            ->where(function ($query) use ($store) {
                $query
                    ->whereDoesntHave('sales')
                    ->orWhereHas('sales', fn ($salesQuery) => $salesQuery->where('store_id', $store->id));
            })
            ->count();

        $alreadyClosed = DailyClose::query()
            ->whereDate('close_date', $closeDate)
            ->exists();

        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
            ],
            'close_date' => $closeDate,
            'already_closed' => $alreadyClosed,
            'open_shift_count' => $openShiftCount,
            'closed_shift_count' => $closedShiftCount,
            'sales_count' => $salesCount,
            'totals' => [
                'total_sales' => round($totalSales, 2),
                'total_purchases' => round($totalPurchases, 2),
                'total_cash_sales' => round($totalCashSales, 2),
                'total_non_cash_sales' => round($totalNonCashSales, 2),
                'total_shift_difference' => round($totalShiftDifference, 2),
                'total_discount' => round($totalDiscount, 2),
                'total_tax' => round($totalTax, 2),
            ],
            'can_close' => ! $alreadyClosed && $openShiftCount === 0,
        ];
    }
}
