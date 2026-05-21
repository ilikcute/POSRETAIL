<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspended_carts', function (Blueprint $table) {
            $table->id();
            // Kode Antrean / Struk Gantung Unik (bisa berupa nomor antrean Q-101 atau barcode)
            $table->string('queue_code')->unique();
            $table->foreignId('station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // Kasir pembuat
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete(); // Member (jika ada)
            
            $table->integer('total_items')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('suspended_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suspended_cart_id')->constrained('suspended_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            
            $table->decimal('qty', 15, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspended_cart_items');
        Schema::dropIfExists('suspended_carts');
    }
};
