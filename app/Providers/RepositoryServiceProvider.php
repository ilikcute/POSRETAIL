<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\Master\StoreRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\StoreRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Auth\UserRepositoryInterface::class,
            \App\Repositories\Eloquent\Auth\UserRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\StationRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\StationRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\WarehouseRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\WarehouseRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\RackRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\RackRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\CategoryRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\CategoryRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\UnitRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\UnitRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\BrandRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\BrandRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\ProductRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\ProductRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\ProductVariantRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\ProductVariantRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Inventory\ProductStockRepositoryInterface::class,
            \App\Repositories\Eloquent\Inventory\ProductStockRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\CustomerRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\CustomerRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Master\SupplierRepositoryInterface::class,
            \App\Repositories\Eloquent\Master\SupplierRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Purchase\PurchaseRepositoryInterface::class,
            \App\Repositories\Eloquent\Purchase\PurchaseRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Sales\SaleRepositoryInterface::class,
            \App\Repositories\Eloquent\Sales\SaleRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Sales\PromotionRepositoryInterface::class,
            \App\Repositories\Eloquent\Sales\PromotionRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Sales\ShiftRepositoryInterface::class,
            \App\Repositories\Eloquent\Sales\ShiftRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Sales\DailyCloseRepositoryInterface::class,
            \App\Repositories\Eloquent\Sales\DailyCloseRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Finance\MonthEndRepositoryInterface::class,
            \App\Repositories\Eloquent\Finance\MonthEndRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Finance\CashTransactionRepositoryInterface::class,
            \App\Repositories\Eloquent\Finance\CashTransactionRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Finance\AccountRepositoryInterface::class,
            \App\Repositories\Eloquent\Finance\AccountRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface::class,
            \App\Repositories\Eloquent\Finance\JournalEntryRepository::class
        );
        
        $this->app->bind(
            \App\Repositories\Contracts\Sales\LoyaltyTransactionRepositoryInterface::class,
            \App\Repositories\Eloquent\Sales\LoyaltyTransactionRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\Inventory\StockOpnameRepositoryInterface::class,
            \App\Repositories\Eloquent\Inventory\StockOpnameRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\Inventory\StockDisposalRepositoryInterface::class,
            \App\Repositories\Eloquent\Inventory\StockDisposalRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\Sales\SuspendedCartRepositoryInterface::class,
            \App\Repositories\Eloquent\Sales\SuspendedCartRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
