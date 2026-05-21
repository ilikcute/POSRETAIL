<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            StoreSeeder::class,
            AccountSeeder::class,
            StationSeeder::class,
            WarehouseSeeder::class,
            CategorySeeder::class,
            UnitSeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
            ProductVariantSeeder::class,
            ProductStockSeeder::class,
            RackSeeder::class,
            CustomerSeeder::class,
            SupplierSeeder::class,
            PurchaseSeeder::class,
            PromotionSeeder::class,
            ShiftSeeder::class,
            CashTransactionSeeder::class,
            SaleSeeder::class,
            StockOpnameSeeder::class,
            StockDisposalSeeder::class,
            DailyCloseSeeder::class,
            MonthEndSeeder::class,
        ]);
    }
}
