<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_disposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_disposal_id')->constrained('stock_disposals')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            
            $table->decimal('qty', 15, 2); // Jumlah barang yang dimusnahkan
            $table->decimal('unit_cost', 15, 2); // Harga beli pokok per unit saat dimusnahkan
            $table->decimal('subtotal', 15, 2); // qty * unit_cost
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_disposal_items');
    }
};
