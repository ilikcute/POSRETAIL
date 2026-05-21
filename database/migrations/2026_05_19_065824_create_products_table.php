<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->unique()->nullable();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('wholesale_price', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('safety_stock', 15, 2)->default(0);
            $table->decimal('reorder_point', 15, 2)->default(0);
            $table->integer('lead_time')->default(0)->comment('Dalam hari');
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_consignment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
