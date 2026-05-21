<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Master\StoreController;
use App\Http\Controllers\Api\Auth\AuthController;

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

    // Master Data
    Route::apiResource('stores', StoreController::class);
    Route::apiResource('stations', App\Http\Controllers\Api\Master\StationController::class);
    Route::apiResource('warehouses', App\Http\Controllers\Api\Master\WarehouseController::class);
    Route::apiResource('racks', App\Http\Controllers\Api\Master\RackController::class);
    Route::get('racks/{id}/planogram', [App\Http\Controllers\Api\Master\RackController::class, 'planogram']);
    Route::post('racks/{id}/planogram', [App\Http\Controllers\Api\Master\RackController::class, 'updatePlanogram']);
    
    // Master Product
    Route::apiResource('categories', App\Http\Controllers\Api\Master\CategoryController::class);
    Route::apiResource('units', App\Http\Controllers\Api\Master\UnitController::class);
    Route::apiResource('brands', App\Http\Controllers\Api\Master\BrandController::class);
    Route::apiResource('products', App\Http\Controllers\Api\Master\ProductController::class);
    Route::apiResource('product-variants', App\Http\Controllers\Api\Master\ProductVariantController::class);
    Route::apiResource('product-stocks', App\Http\Controllers\Api\Inventory\ProductStockController::class);
    
    // CRM
    Route::apiResource('customers', App\Http\Controllers\Api\Master\CustomerController::class);
    Route::apiResource('suppliers', App\Http\Controllers\Api\Master\SupplierController::class);
    
    // Inventory Movements
    Route::apiResource('purchases', App\Http\Controllers\Api\Purchase\PurchaseController::class);
    
    // POS / Sales
    Route::apiResource('promotions', App\Http\Controllers\Api\Sales\PromotionController::class);
    Route::apiResource('sales', App\Http\Controllers\Api\Sales\SaleController::class);
    Route::post('sales/{id}/print-receipt', [App\Http\Controllers\Api\Sales\SaleController::class, 'printReceipt']);
    
    // Shift & Closing Accounting
    Route::apiResource('shifts', App\Http\Controllers\Api\Sales\ShiftController::class);
    Route::apiResource('daily-closes', App\Http\Controllers\Api\Sales\DailyCloseController::class);
    Route::apiResource('month-ends', App\Http\Controllers\Api\Finance\MonthEndController::class);
    Route::apiResource('cash-transactions', App\Http\Controllers\Api\Finance\CashTransactionController::class);
    
    // Double Entry Accounting
    Route::apiResource('accounts', App\Http\Controllers\Api\Finance\AccountController::class);
    Route::apiResource('journal-entries', App\Http\Controllers\Api\Finance\JournalEntryController::class);
    
    // Financial Reports
    Route::get('reports/balance-sheet', [App\Http\Controllers\Api\Finance\FinancialReportController::class, 'balanceSheet']);
    Route::get('reports/profit-loss', [App\Http\Controllers\Api\Finance\FinancialReportController::class, 'profitLoss']);
    Route::get('reports/cash-flow', [App\Http\Controllers\Api\Finance\FinancialReportController::class, 'cashFlow']);
    
    // Loyalty Membership Points/Cashback
    Route::apiResource('loyalty-transactions', App\Http\Controllers\Api\Sales\LoyaltyTransactionController::class);

    // Stock Opname
    Route::apiResource('stock-opnames', App\Http\Controllers\Api\Inventory\StockOpnameController::class);

    // Stock Disposal (Pemusnahan)
    Route::apiResource('stock-disposals', App\Http\Controllers\Api\Inventory\StockDisposalController::class);

    // Consolidated Inventory Module
    Route::get('inventory/stock-status', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'stockStatus']);
    Route::get('inventory/stock-card/{productId}', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'stockCard']);
    Route::get('inventory/valuation', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'valuation']);

    // Price Tags Generator
    Route::get('price-tags/generate', [App\Http\Controllers\Api\Master\PriceTagController::class, 'generate']);

    // Inventory Intelligence & Business Analytics
    Route::get('intelligence/stock-alerts', [App\Http\Controllers\Api\Inventory\InventoryIntelligenceController::class, 'stockAlerts']);
    Route::get('intelligence/best-sellers', [App\Http\Controllers\Api\Inventory\InventoryIntelligenceController::class, 'bestSellers']);
    Route::get('intelligence/promo-performance', [App\Http\Controllers\Api\Inventory\InventoryIntelligenceController::class, 'promoPerformance']);

    // Auto-Replenishment & Smart PO drafts
    Route::get('replenishment/suggestions', [App\Http\Controllers\Api\Inventory\AutoReplenishmentController::class, 'suggestions']);
    Route::post('replenishment/create-drafts', [App\Http\Controllers\Api\Inventory\AutoReplenishmentController::class, 'createDrafts']);

    // Debt & Receivable Ledger (Utang & Piutang)
    Route::get('debt/ap-ledger', [App\Http\Controllers\Api\Finance\DebtLedgerController::class, 'apLedger']);
    Route::get('debt/ap-aging', [App\Http\Controllers\Api\Finance\DebtLedgerController::class, 'apAging']);
    Route::post('debt/pay-ap', [App\Http\Controllers\Api\Finance\DebtLedgerController::class, 'payAp']);
    Route::get('debt/ar-ledger', [App\Http\Controllers\Api\Finance\DebtLedgerController::class, 'arLedger']);
    Route::get('debt/ar-aging', [App\Http\Controllers\Api\Finance\DebtLedgerController::class, 'arAging']);
    Route::post('debt/receive-ar', [App\Http\Controllers\Api\Finance\DebtLedgerController::class, 'receiveAr']);

    // Consignment & PPN Tax Compliance
    Route::get('tax/reconciliation', [App\Http\Controllers\Api\Finance\ConsignmentTaxController::class, 'taxReconciliation']);
    Route::get('consignment/ledger', [App\Http\Controllers\Api\Finance\ConsignmentTaxController::class, 'consignmentLedger']);
    Route::post('consignment/settle', [App\Http\Controllers\Api\Finance\ConsignmentTaxController::class, 'settleConsignment']);

    // Smart Pricing Safeguard & Margin Control
    Route::post('pricing/set-rules', [App\Http\Controllers\Api\Sales\PricingSafeguardController::class, 'setPriceRules']);
    Route::post('pricing/validate-promo', [App\Http\Controllers\Api\Sales\PricingSafeguardController::class, 'validatePromoMargin']);

    // Cash Pull & Drawer Limit Safeguard (Setor Tengah)
    Route::get('cash-pull/check/{stationId}', [App\Http\Controllers\Api\Sales\CashPullController::class, 'checkDrawerLimit']);
    Route::post('cash-pull/execute', [App\Http\Controllers\Api\Sales\CashPullController::class, 'executeCashPull']);

    // Centralized Suspended Carts & Shared Queuing
    Route::post('suspended-carts/suspend', [App\Http\Controllers\Api\Sales\SuspendedCartController::class, 'suspendCart']);
    Route::get('suspended-carts/pending', [App\Http\Controllers\Api\Sales\SuspendedCartController::class, 'getPendingCarts']);
    Route::get('suspended-carts/retrieve/{queueCode}', [App\Http\Controllers\Api\Sales\SuspendedCartController::class, 'retrieveCart']);
    Route::post('suspended-carts/complete', [App\Http\Controllers\Api\Sales\SuspendedCartController::class, 'completeCheckout']);

    // Cashier Remittance & Drawer Close Reconciliation
    Route::get('remittance/summary/{shiftId}', [App\Http\Controllers\Api\Sales\StationRemittanceController::class, 'getSummary']);
    Route::post('remittance/submit', [App\Http\Controllers\Api\Sales\StationRemittanceController::class, 'submitRemittance']);
});
