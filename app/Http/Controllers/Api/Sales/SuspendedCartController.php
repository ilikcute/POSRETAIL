<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CompleteCheckoutRequest;
use App\Http\Requests\Sales\SuspendCartRequest;
use App\Models\Finance\JournalEntry;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * Menggantung Transaksi Kasir (Parkir Keranjang)
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

            // 1. Buat Header Transaksi Gantung (menggunakan repository!)
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
        // Menggunakan custom repository method!
        $carts = $this->suspendedCartRepo->getPendingCartsWithRelations();

        return $this->successResponse($carts, 'Pending suspended carts retrieved successfully');
    }

    /**
     * 3. GET /api/suspended-carts/retrieve/{queueCode}
     * Memanggil / Mengambil Keranjang Gantung Menggunakan Kode Antrean
     */
    public function retrieveCart(string $queueCode): JsonResponse
    {
        // Menggunakan custom repository method!
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
        $bankAccountCode = $request->input('bank_account_code');

        $cart = SuspendedCart::where('queue_code', $queueCode)
            ->where('status', 'pending')
            ->with('items')
            ->firstOrFail();

        // Cari shift aktif di stasiun kasir target pembayaran
        $shift = Shift::where('station_id', $targetStationId)
            ->where('status', 'open')
            ->orderBy('id', 'desc')
            ->first();

        if (! $shift) {
            return $this->errorResponse('Gagal checkout! Stasiun kasir target tidak memiliki shift aktif.', 400);
        }

        $result = DB::transaction(function () use ($cart, $shift, $paymentMethod, $targetStationId) {
            $invoiceNo = 'INV-POS-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
            $storeId = Store::first()->id;

            // 1. BUAT PENJUALAN POS LENGKAP MENGGUNAKAN SALEREPOSITORY
            // Ini otomatis: mengisi detail item, memotong stok gudang, dan memposting Jurnal Ganda Akuntansi (COGS & Sales)!
            $sale = $this->saleRepo->create([
                'store_id' => $storeId,
                'warehouse_id' => Warehouse::first()->id,
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

            // 2. UPDATE STATUS KERANJANG GANTUNG MENJADI SELESAI (menggunakan repository!)
            $this->suspendedCartRepo->update($cart->id, [
                'status' => 'completed',
            ]);

            // 3. UPDATE SHIFT REVENUE & EXPECTED CASH (menggunakan repository!)
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

            // Dapatkan Jurnal Entry terbaru yang otomatis dibuat oleh SaleRepository
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
    }
}
