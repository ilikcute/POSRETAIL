<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->unique()->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('wholesale_price', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
