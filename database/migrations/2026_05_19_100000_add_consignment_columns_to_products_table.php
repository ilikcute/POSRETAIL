<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Tipe Pembelian: outright (beli putus), consignment (titip jual)
            $table->enum('purchase_type', ['outright', 'consignment'])->default('outright')->after('is_active');
            // Komisi Toko (persentase, contoh: 20% untuk toko, sisanya 80% milik supplier)
            $table->decimal('consignment_commission_fee', 5, 2)->default(0.00)->after('purchase_type');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            // Catat tipe pembelian produk pada saat terjual untuk menjaga konsistensi data historis
            $table->enum('purchase_type', ['outright', 'consignment'])->default('outright')->after('subtotal');
            // Nilai komisi konsinyasi yang didapat toko pada item ini
            $table->decimal('consignment_commission_amount', 15, 2)->default(0.00)->after('purchase_type');
            // Nilai bersih yang menjadi utang konsinyasi ke supplier
            $table->decimal('consignment_payable_amount', 15, 2)->default(0.00)->after('consignment_commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['purchase_type', 'consignment_commission_fee']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_type', 'consignment_commission_amount', 'consignment_payable_amount']);
        });
    }
};
