<?php

namespace App\Providers;

use App\Repositories\Contracts\Auth\RoleRepositoryInterface;
use App\Repositories\Contracts\Auth\UserRepositoryInterface;
use App\Repositories\Contracts\Finance\AccountRepositoryInterface;
use App\Repositories\Contracts\Finance\CashTransactionRepositoryInterface;
use App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface;
use App\Repositories\Contracts\Finance\MonthEndRepositoryInterface;
use App\Repositories\Contracts\Inventory\ProductStockRepositoryInterface;
use App\Repositories\Contracts\Inventory\StockDisposalRepositoryInterface;
use App\Repositories\Contracts\Inventory\StockOpnameRepositoryInterface;
use App\Repositories\Contracts\Master\BrandRepositoryInterface;
use App\Repositories\Contracts\Master\CategoryRepositoryInterface;
use App\Repositories\Contracts\Master\CustomerRepositoryInterface;
use App\Repositories\Contracts\Master\ProductRepositoryInterface;
use App\Repositories\Contracts\Master\ProductVariantRepositoryInterface;
use App\Repositories\Contracts\Master\RackRepositoryInterface;
use App\Repositories\Contracts\Master\SettingRepositoryInterface;
use App\Repositories\Contracts\Master\StationRepositoryInterface;
use App\Repositories\Contracts\Master\StoreRepositoryInterface;
use App\Repositories\Contracts\Master\SupplierRepositoryInterface;
use App\Repositories\Contracts\Master\UnitRepositoryInterface;
use App\Repositories\Contracts\Master\WarehouseRepositoryInterface;
use App\Repositories\Contracts\Purchase\PurchaseRepositoryInterface;
use App\Repositories\Contracts\Sales\DailyCloseRepositoryInterface;
use App\Repositories\Contracts\Sales\LoyaltyTransactionRepositoryInterface;
use App\Repositories\Contracts\Sales\PromotionRepositoryInterface;
use App\Repositories\Contracts\Sales\SaleRepositoryInterface;
use App\Repositories\Contracts\Sales\ShiftRepositoryInterface;
use App\Repositories\Contracts\Sales\SuspendedCartRepositoryInterface;
use App\Repositories\Eloquent\Auth\RoleRepository;
use App\Repositories\Eloquent\Auth\UserRepository;
use App\Repositories\Eloquent\Finance\AccountRepository;
use App\Repositories\Eloquent\Finance\CashTransactionRepository;
use App\Repositories\Eloquent\Finance\JournalEntryRepository;
use App\Repositories\Eloquent\Finance\MonthEndRepository;
use App\Repositories\Eloquent\Inventory\ProductStockRepository;
use App\Repositories\Eloquent\Inventory\StockDisposalRepository;
use App\Repositories\Eloquent\Inventory\StockOpnameRepository;
use App\Repositories\Eloquent\Master\BrandRepository;
use App\Repositories\Eloquent\Master\CategoryRepository;
use App\Repositories\Eloquent\Master\CustomerRepository;
use App\Repositories\Eloquent\Master\ProductRepository;
use App\Repositories\Eloquent\Master\ProductVariantRepository;
use App\Repositories\Eloquent\Master\RackRepository;
use App\Repositories\Eloquent\Master\SettingRepository;
use App\Repositories\Eloquent\Master\StationRepository;
use App\Repositories\Eloquent\Master\StoreRepository;
use App\Repositories\Eloquent\Master\SupplierRepository;
use App\Repositories\Eloquent\Master\UnitRepository;
use App\Repositories\Eloquent\Master\WarehouseRepository;
use App\Repositories\Eloquent\Purchase\PurchaseRepository;
use App\Repositories\Eloquent\Sales\DailyCloseRepository;
use App\Repositories\Eloquent\Sales\LoyaltyTransactionRepository;
use App\Repositories\Eloquent\Sales\PromotionRepository;
use App\Repositories\Eloquent\Sales\SaleRepository;
use App\Repositories\Eloquent\Sales\ShiftRepository;
use App\Repositories\Eloquent\Sales\SuspendedCartRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );

        $this->app->bind(
            StoreRepositoryInterface::class,
            StoreRepository::class
        );

        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );

        $this->app->bind(
            StationRepositoryInterface::class,
            StationRepository::class
        );

        $this->app->bind(
            WarehouseRepositoryInterface::class,
            WarehouseRepository::class
        );

        $this->app->bind(
            RackRepositoryInterface::class,
            RackRepository::class
        );

        $this->app->bind(
            CategoryRepositoryInterface::class,
            CategoryRepository::class
        );

        $this->app->bind(
            UnitRepositoryInterface::class,
            UnitRepository::class
        );

        $this->app->bind(
            BrandRepositoryInterface::class,
            BrandRepository::class
        );

        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class
        );

        $this->app->bind(
            ProductVariantRepositoryInterface::class,
            ProductVariantRepository::class
        );

        $this->app->bind(
            ProductStockRepositoryInterface::class,
            ProductStockRepository::class
        );

        $this->app->bind(
            CustomerRepositoryInterface::class,
            CustomerRepository::class
        );

        $this->app->bind(
            SupplierRepositoryInterface::class,
            SupplierRepository::class
        );

        $this->app->bind(
            PurchaseRepositoryInterface::class,
            PurchaseRepository::class
        );

        $this->app->bind(
            SaleRepositoryInterface::class,
            SaleRepository::class
        );

        $this->app->bind(
            PromotionRepositoryInterface::class,
            PromotionRepository::class
        );

        $this->app->bind(
            ShiftRepositoryInterface::class,
            ShiftRepository::class
        );

        $this->app->bind(
            DailyCloseRepositoryInterface::class,
            DailyCloseRepository::class
        );

        $this->app->bind(
            MonthEndRepositoryInterface::class,
            MonthEndRepository::class
        );

        $this->app->bind(
            CashTransactionRepositoryInterface::class,
            CashTransactionRepository::class
        );

        $this->app->bind(
            AccountRepositoryInterface::class,
            AccountRepository::class
        );

        $this->app->bind(
            JournalEntryRepositoryInterface::class,
            JournalEntryRepository::class
        );

        $this->app->bind(
            LoyaltyTransactionRepositoryInterface::class,
            LoyaltyTransactionRepository::class
        );

        $this->app->bind(
            StockOpnameRepositoryInterface::class,
            StockOpnameRepository::class
        );

        $this->app->bind(
            StockDisposalRepositoryInterface::class,
            StockDisposalRepository::class
        );

        $this->app->bind(
            SuspendedCartRepositoryInterface::class,
            SuspendedCartRepository::class
        );

        $this->app->bind(
            SettingRepositoryInterface::class,
            SettingRepository::class
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
