<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            
            $table->decimal('system_qty', 15, 2);
            $table->decimal('physical_qty', 15, 2);
            $table->decimal('discrepancy', 15, 2); // physical_qty - system_qty
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('discrepancy_value', 15, 2); // discrepancy * unit_cost
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
