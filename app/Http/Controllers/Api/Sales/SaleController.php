<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\PaymentFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\UpdateSaleRequest;
use App\Jobs\SendSaleConfirmationEmail;
use App\Models\Inventory\ProductStock;
use App\Models\Master\Product;
use App\Models\Sales\Shift;
use App\Repositories\Contracts\Sales\SaleRepositoryInterface;
use App\Services\ThermalPrintService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    use ApiResponseTrait;

    protected SaleRepositoryInterface $saleRepository;

    public function __construct(SaleRepositoryInterface $saleRepository)
    {
        $this->saleRepository = $saleRepository;
    }

    public function index(): JsonResponse
    {
        $sales = $this->saleRepository->all();

        return $this->successResponse($sales, 'Sales retrieved successfully');
    }

    public function store(StoreSaleRequest $request): JsonResponse|RedirectResponse
    {
        $warehouseId = $request->input('warehouse_id');
        $items = $request->input('items');

        // 1. Cek Shift Kasir Aktif
        $shiftId = $request->input('shift_id');
        if (! $shiftId) {
            $activeShift = Shift::where('user_id', auth()->id() ?? 1)
                ->where('status', 'open')
                ->orderBy('id', 'desc')
                ->first();
            if (! $activeShift) {
                if ($request->wantsJson()) {
                    return $this->errorResponse('Gagal checkout! Tidak ada shift aktif untuk kasir ini.', 400);
                }

                return redirect()->back()->with('error', 'Gagal checkout! Tidak ada shift aktif untuk kasir ini.');
            }
            $shiftId = $activeShift->id;
        }

        try {
            $result = DB::transaction(function () use ($request, $items, $warehouseId, $shiftId) {
                // 2. Validasi Stok Tersedia (Stock > 0 & Cukup)
                foreach ($items as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $variantId = $item['product_variant_id'] ?? null;

                    $stock = ProductStock::where('product_id', $item['product_id'])
                        ->where('product_variant_id', $variantId)
                        ->where('warehouse_id', $warehouseId)
                        ->first();

                    $stockQty = $stock ? (float) $stock->qty : 0.0;
                    $requestedQty = (float) $item['qty'];

                    if ($stockQty <= 0) {
                        throw new InsufficientStockException("Stok produk '{$product->name}' telah habis di gudang terpilih.");
                    }

                    if ($stockQty < $requestedQty) {
                        throw new InsufficientStockException("Stok produk '{$product->name}' tidak mencukupi. Tersedia: {$stockQty}, Diminta: {$requestedQty}.");
                    }
                }

                // 3. Hitung Grand Total Penjualan
                $totalAmount = 0;
                $totalTax = 0;
                foreach ($items as $item) {
                    $totalAmount += ($item['qty'] * $item['unit_price']);
                    $totalTax += ($item['tax'] ?? 0);
                }

                $discountAmount = (float) ($request->input('discount_amount') ?? 0);
                $pointsRedeemed = (float) ($request->input('points_redeemed') ?? 0);
                $pointsDiscount = $pointsRedeemed * 1.0;

                $grandTotal = ($totalAmount + $totalTax) - $discountAmount - $pointsDiscount;
                if ($grandTotal < 0) {
                    $grandTotal = 0;
                }

                // 4. Charge Payment via Payment Gateway
                $paymentMethod = $request->input('payment_method');
                if (in_array($paymentMethod, ['qris', 'card', 'credit_card'])) {
                    // Simulasi charge ke gateway (misal Stripe / QRIS Provider)
                    $paymentSuccess = true;

                    // Pemicu simulasi gagal untuk testing
                    if ($request->input('simulate_payment_fail') === true || $request->input('card_number') === '4111111111111112') {
                        $paymentSuccess = false;
                    }

                    if (! $paymentSuccess) {
                        $formattedTotal = number_format($grandTotal, 0, ',', '.');
                        throw new PaymentFailedException("Transaksi pembayaran senilai Rp {$formattedTotal} via Payment Gateway ditolak.");
                    }
                }

                // 5. Buat Record Order/Sale
                $payload = array_merge($request->validated(), [
                    'shift_id' => $shiftId,
                    'status' => 'completed', // Status completed = Paid
                ]);

                $sale = $this->saleRepository->create($payload);

                // 6. Kirim Email Konfirmasi secara asinkron
                SendSaleConfirmationEmail::dispatch($sale);

                return $sale;
            });

            if ($request->wantsJson()) {
                return $this->successResponse($result, 'Transaksi penjualan berhasil diproses.', 201);
            }

            return redirect()->back()->with('success', 'Transaksi penjualan berhasil diproses.');

        } catch (InsufficientStockException $e) {
            Log::warning('Checkout Gagal (Stock): '.$e->getMessage());

            if ($request->wantsJson()) {
                return $this->errorResponse($e->getMessage(), 422);
            }

            return redirect()->back()->withErrors(['stock' => $e->getMessage()]);

        } catch (PaymentFailedException $e) {
            Log::error('Checkout Gagal (Payment): '.$e->getMessage());

            if ($request->wantsJson()) {
                return $this->errorResponse($e->getMessage(), 402); // 402 Payment Required
            }

            return redirect()->back()->withErrors(['payment' => $e->getMessage()]);

        } catch (\Exception $e) {
            Log::error('Checkout Gagal (System Error): '.$e->getMessage());

            if ($request->wantsJson()) {
                return $this->errorResponse('Terjadi kesalahan internal: '.$e->getMessage(), 500);
            }

            return redirect()->back()->withErrors(['error' => 'Terjadi kesalahan sistem.']);
        }
    }

    public function show($id): JsonResponse
    {
        $sale = $this->saleRepository->findOrFail($id);
        $sale->load(['items.product', 'customer', 'cashier', 'store', 'station']);

        return $this->successResponse($sale, 'Sale transaction retrieved successfully');
    }

    public function update(UpdateSaleRequest $request, $id): JsonResponse|RedirectResponse
    {
        $sale = $this->saleRepository->update($id, $request->except('items'));

        if ($request->wantsJson()) {
            return $this->successResponse($sale, 'Sale status updated successfully');
        }

        return redirect()->back()->with('success', 'Sale status updated successfully');
    }

    public function destroy($id): JsonResponse|RedirectResponse
    {
        $this->saleRepository->delete($id);

        if (request()->wantsJson()) {
            return $this->successResponse(null, 'Sale document deleted successfully');
        }

        return redirect()->back()->with('success', 'Sale document deleted successfully');
    }

    /**
     * Cetak Struk Ke Printer Thermal ESC/POS
     */
    public function printReceipt(Request $request, $id): JsonResponse
    {
        $connectorType = $request->input('connector_type', 'network');
        $connectorTarget = $request->input('connector_target', '192.168.1.200');
        $paperWidth = (int) $request->input('paper_width', 32);

        $printService = new ThermalPrintService;
        $result = $printService->printReceipt($id, $connectorType, $connectorTarget, $paperWidth);

        if ($result['success']) {
            return $this->successResponse(null, $result['message']);
        }

        return $this->errorResponse($result['message'], 400);
    }
}
