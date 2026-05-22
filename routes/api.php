<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Finance\AccountController;
use App\Http\Controllers\Api\Finance\CashTransactionController;
use App\Http\Controllers\Api\Finance\ConsignmentTaxController;
use App\Http\Controllers\Api\Finance\DebtLedgerController;
use App\Http\Controllers\Api\Finance\FinancialReportController;
use App\Http\Controllers\Api\Finance\JournalEntryController;
use App\Http\Controllers\Api\Finance\MonthEndController;
use App\Http\Controllers\Api\Inventory\AutoReplenishmentController;
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\Api\Inventory\InventoryIntelligenceController;
use App\Http\Controllers\Api\Inventory\ProductStockController;
use App\Http\Controllers\Api\Inventory\StockDisposalController;
use App\Http\Controllers\Api\Inventory\StockOpnameController;
use App\Http\Controllers\Api\Master\BrandController;
use App\Http\Controllers\Api\Master\CategoryController;
use App\Http\Controllers\Api\Master\CustomerController;
use App\Http\Controllers\Api\Master\PriceTagController;
use App\Http\Controllers\Api\Master\ProductController;
use App\Http\Controllers\Api\Master\ProductVariantController;
use App\Http\Controllers\Api\Master\RackController;
use App\Http\Controllers\Api\Master\StationController;
use App\Http\Controllers\Api\Master\StoreController;
use App\Http\Controllers\Api\Master\SupplierController;
use App\Http\Controllers\Api\Master\UnitController;
use App\Http\Controllers\Api\Master\WarehouseController;
use App\Http\Controllers\Api\Purchase\PurchaseController;
use App\Http\Controllers\Api\Sales\CashPullController;
use App\Http\Controllers\Api\Sales\DailyCloseController;
use App\Http\Controllers\Api\Sales\LoyaltyTransactionController;
use App\Http\Controllers\Api\Sales\PricingSafeguardController;
use App\Http\Controllers\Api\Sales\PromotionController;
use App\Http\Controllers\Api\Sales\SaleController;
use App\Http\Controllers\Api\Sales\ShiftController;
use App\Http\Controllers\Api\Sales\StationRemittanceController;
use App\Http\Controllers\Api\Sales\SuspendedCartController;
use Illuminate\Support\Facades\Route;

// Public Auth Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected Routes (Butuh Token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/users', [AuthController::class, 'users']);

    // Master Data
    Route::apiResource('stores', StoreController::class);
    Route::apiResource('stations', StationController::class);
    Route::apiResource('warehouses', WarehouseController::class);
    Route::apiResource('racks', RackController::class);
    Route::get('racks/{id}/planogram', [RackController::class, 'planogram']);
    Route::post('racks/{id}/planogram', [RackController::class, 'updatePlanogram']);

    // Master Product
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('brands', BrandController::class);
    // Product Excel routes must be before apiResource to avoid {product} wildcard capture
    Route::get('products/export', [ProductController::class, 'export']);
    Route::get('products/import-template', [ProductController::class, 'downloadTemplate']);
    Route::post('products/import', [ProductController::class, 'import']);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-variants', ProductVariantController::class);
    Route::apiResource('product-stocks', ProductStockController::class);

    // CRM
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('suppliers', SupplierController::class);

    // Inventory Movements
    Route::apiResource('purchases', PurchaseController::class);

    // POS / Sales
    Route::apiResource('promotions', PromotionController::class);
    Route::apiResource('sales', SaleController::class);
    Route::post('sales/{id}/print-receipt', [SaleController::class, 'printReceipt']);

    // Shift & Closing Accounting
    Route::apiResource('shifts', ShiftController::class);
    Route::get('daily-closes/preview', [DailyCloseController::class, 'preview']);
    Route::apiResource('daily-closes', DailyCloseController::class);
    Route::apiResource('month-ends', MonthEndController::class);
    Route::apiResource('cash-transactions', CashTransactionController::class);

    // Double Entry Accounting
    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('journal-entries', JournalEntryController::class);

    // Financial Reports
    Route::get('reports/balance-sheet', [FinancialReportController::class, 'balanceSheet']);
    Route::get('reports/profit-loss', [FinancialReportController::class, 'profitLoss']);
    Route::get('reports/cash-flow', [FinancialReportController::class, 'cashFlow']);

    // Loyalty Membership Points/Cashback
    Route::apiResource('loyalty-transactions', LoyaltyTransactionController::class);

    // Stock Opname
    Route::apiResource('stock-opnames', StockOpnameController::class);

    // Stock Disposal (Pemusnahan)
    Route::apiResource('stock-disposals', StockDisposalController::class);

    // Consolidated Inventory Module
    Route::get('inventory/stock-status', [InventoryController::class, 'stockStatus']);
    Route::get('inventory/stock-card/{productId}', [InventoryController::class, 'stockCard']);
    Route::get('inventory/valuation', [InventoryController::class, 'valuation']);

    // Price Tags Generator
    Route::get('price-tags/generate', [PriceTagController::class, 'generate']);

    // Inventory Intelligence & Business Analytics
    Route::get('intelligence/stock-alerts', [InventoryIntelligenceController::class, 'stockAlerts']);
    Route::get('intelligence/best-sellers', [InventoryIntelligenceController::class, 'bestSellers']);
    Route::get('intelligence/promo-performance', [InventoryIntelligenceController::class, 'promoPerformance']);

    // Auto-Replenishment & Smart PO drafts
    Route::get('replenishment/suggestions', [AutoReplenishmentController::class, 'suggestions']);
    Route::post('replenishment/create-drafts', [AutoReplenishmentController::class, 'createDrafts']);

    // Debt & Receivable Ledger (Utang & Piutang)
    Route::get('debt/ap-ledger', [DebtLedgerController::class, 'apLedger']);
    Route::get('debt/ap-aging', [DebtLedgerController::class, 'apAging']);
    Route::post('debt/pay-ap', [DebtLedgerController::class, 'payAp']);
    Route::get('debt/ar-ledger', [DebtLedgerController::class, 'arLedger']);
    Route::get('debt/ar-aging', [DebtLedgerController::class, 'arAging']);
    Route::post('debt/receive-ar', [DebtLedgerController::class, 'receiveAr']);

    // Consignment & PPN Tax Compliance
    Route::get('tax/reconciliation', [ConsignmentTaxController::class, 'taxReconciliation']);
    Route::get('consignment/ledger', [ConsignmentTaxController::class, 'consignmentLedger']);
    Route::post('consignment/settle', [ConsignmentTaxController::class, 'settleConsignment']);

    // Smart Pricing Safeguard & Margin Control
    Route::get('pricing/safeguards', [PricingSafeguardController::class, 'index']);
    Route::post('pricing/set-rules', [PricingSafeguardController::class, 'setPriceRules']);
    Route::post('pricing/validate-promo', [PricingSafeguardController::class, 'validatePromoMargin']);

    // Cash Pull & Drawer Limit Safeguard (Setor Tengah)
    Route::get('cash-pull/check/{station}', [CashPullController::class, 'checkDrawerLimit']);
    Route::post('cash-pull/execute', [CashPullController::class, 'executeCashPull']);

    // Centralized Suspended Carts & Shared Queuing
    Route::post('suspended-carts/suspend', [SuspendedCartController::class, 'suspendCart']);
    Route::get('suspended-carts/pending', [SuspendedCartController::class, 'getPendingCarts']);
    Route::get('suspended-carts/retrieve/{queueCode}', [SuspendedCartController::class, 'retrieveCart']);
    Route::post('suspended-carts/complete', [SuspendedCartController::class, 'completeCheckout']);
    Route::put('suspended-carts/void/{queueCode}', [SuspendedCartController::class, 'voidCart']);
    Route::post('suspended-carts/reset', [SuspendedCartController::class, 'resetCarts']);

    // Cashier Remittance & Drawer Close Reconciliation
    Route::get('remittance/summary/{shiftId}', [StationRemittanceController::class, 'getSummary']);
    Route::post('remittance/submit', [StationRemittanceController::class, 'submitRemittance']);
});
