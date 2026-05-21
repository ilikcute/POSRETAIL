<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('rack_id')->nullable()->constrained('racks')->nullOnDelete();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('min_qty', 15, 2)->default(0);
            $table->timestamps();

            // Opsional: Pastikan tidak ada duplikasi stok ganda untuk kombinasi yang sama (kecuali Anda ingin merekam per batch/lot)
            // $table->unique(['product_id', 'product_variant_id', 'warehouse_id', 'rack_id'], 'unique_product_stock_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
