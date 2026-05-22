<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\PaymentFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CompleteCheckoutRequest;
use App\Http\Requests\Sales\SuspendCartRequest;
use App\Jobs\SendSaleConfirmationEmail;
use App\Models\Finance\JournalEntry;
use App\Models\Inventory\ProductStock;
use App\Models\Master\Store;
use App\Models\Master\Warehouse;
use App\Models\Sales\Shift;
use App\Models\Sales\SuspendedCart;
use App\Models\Sales\SuspendedCartItem;
use App\Repositories\Contracts\Finance\AccountRepositoryInterface;
use App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface;
use App\Repositories\Contracts\Master\StationRepositoryInterface;
use App\Repositories\Contracts\Sales\SaleRepositoryInterface;
use App\Repositories\Contracts\Sales\ShiftRepositoryInterface;
use App\Repositories\Contracts\Sales\SuspendedCartRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuspendedCartController extends Controller
{
    use ApiResponseTrait;

    protected SuspendedCartRepositoryInterface $suspendedCartRepo;

    protected StationRepositoryInterface $stationRepo;

    protected ShiftRepositoryInterface $shiftRepo;

    protected SaleRepositoryInterface $saleRepo;

    protected AccountRepositoryInterface $accountRepo;

    protected JournalEntryRepositoryInterface $journalRepo;

    public function __construct(
        SuspendedCartRepositoryInterface $suspendedCartRepo,
        StationRepositoryInterface $stationRepo,
        ShiftRepositoryInterface $shiftRepo,
        SaleRepositoryInterface $saleRepo,
        AccountRepositoryInterface $accountRepo,
        JournalEntryRepositoryInterface $journalRepo
    ) {
        $this->suspendedCartRepo = $suspendedCartRepo;
        $this->stationRepo = $stationRepo;
        $this->shiftRepo = $shiftRepo;
        $this->saleRepo = $saleRepo;
        $this->accountRepo = $accountRepo;
        $this->journalRepo = $journalRepo;
    }

    /**
     * 1. POST /api/suspended-carts/suspend
     * Menggantung Transaksi Kasir (Parkir Keranjang / Hold)
     */
    public function suspendCart(SuspendCartRequest $request): JsonResponse
    {
        $stationId = $request->input('station_id');
        $customerId = $request->input('customer_id');
        $notes = $request->input('notes');
        $itemsData = $request->input('items');

        $result = DB::transaction(function () use ($stationId, $customerId, $notes, $itemsData) {
            $today = now()->format('Ymd');
            $countToday = SuspendedCart::whereDate('created_at', now()->toDateString())->count();
            $queueCode = 'Q-'.$today.'-'.str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);

            $totalItems = 0;
            $totalAmount = 0.0;

            // 1. Buat Header Transaksi Gantung
            $cart = $this->suspendedCartRepo->create([
                'queue_code' => $queueCode,
                'station_id' => $stationId,
                'user_id' => auth()->id() ?? 1,
                'customer_id' => $customerId,
                'total_items' => 0,
                'total_amount' => 0,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            // 2. Buat Detail Item
            foreach ($itemsData as $item) {
                $subtotal = $item['qty'] * $item['unit_price'];

                SuspendedCartItem::create([
                    'suspended_cart_id' => $cart->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                    'notes' => $item['notes'] ?? null,
                ]);

                $totalItems += $item['qty'];
                $totalAmount += $subtotal;
            }

            // 3. Update nominal total
            $this->suspendedCartRepo->update($cart->id, [
                'total_items' => $totalItems,
                'total_amount' => $totalAmount,
            ]);

            return $cart->load(['items.product', 'customer', 'station']);
        });

        return $this->successResponse($result, 'Cart suspended / Keranjang belanja digantung successfully', 201);
    }

    /**
     * 2. GET /api/suspended-carts/pending
     * Mendapatkan Seluruh Daftar Keranjang Gantung yang Masih Pending
     */
    public function getPendingCarts(): JsonResponse
    {
        $carts = $this->suspendedCartRepo->getPendingCartsWithRelations();

        return $this->successResponse($carts, 'Pending suspended carts retrieved successfully');
    }

    /**
     * 3. GET /api/suspended-carts/retrieve/{queueCode}
     * Memanggil / Mengambil Keranjang Gantung Menggunakan Kode Antrean
     */
    public function retrieveCart(string $queueCode): JsonResponse
    {
        $cart = $this->suspendedCartRepo->findPendingByQueueCode($queueCode);

        if (! $cart) {
            return $this->errorResponse('Keranjang belanja gantung tidak ditemukan atau sudah diselesaikan!', 404);
        }

        return $this->successResponse($cart, 'Suspended cart retrieved successfully');
    }

    /**
     * 4. POST /api/suspended-carts/complete
     * Menyelesaikan Pembayaran Keranjang Gantung di Kasir Target (Auto-Jurnal GL!)
     */
    public function completeCheckout(CompleteCheckoutRequest $request): JsonResponse
    {
        $queueCode = $request->input('queue_code');
        $targetStationId = $request->input('target_station_id');
        $paymentMethod = $request->input('payment_method');

        $cart = SuspendedCart::where('queue_code', $queueCode)
            ->where('status', 'pending')
            ->with('items.product')
            ->first();

        if (! $cart) {
            return $this->errorResponse('Keranjang belanja gantung tidak ditemukan atau sudah diselesaikan!', 404);
        }

        // Cari shift aktif di stasiun kasir target pembayaran
        $shift = Shift::where('station_id', $targetStationId)
            ->where('status', 'open')
            ->orderBy('id', 'desc')
            ->first();

        if (! $shift) {
            return $this->errorResponse('Gagal checkout! Stasiun kasir target tidak memiliki shift aktif.', 400);
        }

        // Tentukan warehouse id (gunakan first atau fallback)
        $warehouseId = Warehouse::first()->id;

        try {
            // A. VALIDASI STOK SEBELUM PEMBAYARAN
            foreach ($cart->items as $item) {
                $product = $item->product;
                $stock = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                $stockQty = $stock ? (float) $stock->qty : 0.0;
                $requestedQty = (float) $item->qty;

                if ($stockQty <= 0) {
                    throw new InsufficientStockException("Stok produk '{$product->name}' telah habis di gudang terpilih.");
                }

                if ($stockQty < $requestedQty) {
                    throw new InsufficientStockException("Stok produk '{$product->name}' tidak mencukupi. Tersedia: {$stockQty}, Diminta: {$requestedQty}.");
                }
            }

            // B. SIMULASI PAYMENT GATEWAY
            if (in_array($paymentMethod, ['qris', 'card'])) {
                $paymentSuccess = true;
                if ($request->input('simulate_payment_fail') === true || $request->input('card_number') === '4111111111111112') {
                    $paymentSuccess = false;
                }

                if (! $paymentSuccess) {
                    $formattedTotal = number_format($cart->total_amount, 0, ',', '.');
                    throw new PaymentFailedException("Transaksi pembayaran senilai Rp {$formattedTotal} via Payment Gateway ditolak.");
                }
            }

            // C. TRANSAKSI EKSEKUSI
            $result = DB::transaction(function () use ($cart, $shift, $paymentMethod, $targetStationId, $warehouseId) {
                $storeId = Store::first()->id;

                // 1. Buat Penjualan POS menggunakan SaleRepository
                $sale = $this->saleRepo->create([
                    'store_id' => $storeId,
                    'warehouse_id' => $warehouseId,
                    'customer_id' => $cart->customer_id,
                    'station_id' => $targetStationId,
                    'shift_id' => $shift->id,
                    'amount_paid' => $cart->total_amount,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'status' => 'completed',
                    'notes' => "Ditarik dari Antrean Gantung: {$cart->queue_code}",
                    'items' => $cart->items->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'qty' => $item->qty,
                            'unit_price' => $item->unit_price,
                        ];
                    })->toArray(),
                ]);

                // 2. Update status keranjang gantung menjadi selesai
                $this->suspendedCartRepo->update($cart->id, [
                    'status' => 'completed',
                ]);

                // 3. Update pendapatan shift kasir
                $updatedSales = $shift->total_sales;
                $updatedCash = $shift->expected_cash;
                $updatedQris = $shift->expected_qris;
                $updatedCard = $shift->expected_card;

                if ($paymentMethod === 'cash') {
                    $updatedSales += $sale->grand_total;
                    $updatedCash += $sale->grand_total;
                } elseif ($paymentMethod === 'qris') {
                    $updatedQris += $sale->grand_total;
                } else {
                    $updatedCard += $sale->grand_total;
                }

                $this->shiftRepo->update($shift->id, [
                    'total_sales' => $updatedSales,
                    'expected_cash' => $updatedCash,
                    'expected_qris' => $updatedQris,
                    'expected_card' => $updatedCard,
                ]);

                // 4. Kirim email konfirmasi secara asinkron
                SendSaleConfirmationEmail::dispatch($sale);

                // Dapatkan Jurnal Entry terbaru
                $latestJournal = JournalEntry::orderBy('id', 'desc')->first();

                return [
                    'sale_id' => $sale->id,
                    'invoice_no' => $sale->invoice_no,
                    'queue_code' => $cart->queue_code,
                    'grand_total' => $sale->grand_total,
                    'payment_method' => $sale->payment_method,
                    'journal_entry' => $latestJournal ? $latestJournal->reference_no : 'JV-AUTO',
                ];
            });

            return $this->successResponse($result, 'Checkout from suspended cart completed and journalized successfully');

        } catch (InsufficientStockException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (PaymentFailedException $e) {
            return $this->errorResponse($e->getMessage(), 402);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * 5. PUT /api/suspended-carts/void/{queueCode}
     * Membatalkan (Void) Transaksi Gantung
     */
    public function voidCart(Request $request, string $queueCode): JsonResponse
    {
        $cart = SuspendedCart::where('queue_code', $queueCode)
            ->where('status', 'pending')
            ->first();

        if (! $cart) {
            return $this->errorResponse('Keranjang belanja gantung tidak ditemukan atau sudah diproses!', 404);
        }

        DB::transaction(function () use ($cart) {
            $this->suspendedCartRepo->update($cart->id, [
                'status' => 'void',
            ]);
        });

        return $this->successResponse($cart->load(['items.product']), 'Keranjang belanja gantung berhasil di-void.');
    }

    /**
     * 6. POST /api/suspended-carts/reset
     * Mengosongkan seluruh antrean belanja gantung stasiun kasir aktif (Reset)
     */
    public function resetCarts(Request $request): JsonResponse
    {
        $stationId = $request->input('station_id');

        if (! $stationId) {
            $activeShift = Shift::where('user_id', auth()->id() ?? 1)
                ->where('status', 'open')
                ->orderBy('id', 'desc')
                ->first();
            $stationId = $activeShift ? $activeShift->station_id : null;
        }

        if (! $stationId) {
            return $this->errorResponse('Gagal meriset! Stasiun kasir tidak teridentifikasi atau tidak ada shift aktif.', 400);
        }

        $count = DB::transaction(function () use ($stationId) {
            return SuspendedCart::where('station_id', $stationId)
                ->where('status', 'pending')
                ->update(['status' => 'void']);
        });

        return $this->successResponse(['reset_count' => $count], 'Semua antrean belanja gantung stasiun kasir berhasil di-reset.');
    }
}
