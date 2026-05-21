<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            
            $table->decimal('qty', 15, 2);
            $table->decimal('unit_price', 15, 2); // Harga jual saat transaksi terjadi
            $table->decimal('cost_price', 15, 2)->default(0); // Harga modal (HPP) untuk laporan Laba/Rugi
            
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
