<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            
            // Tipe transaksi: earn (mendapat poin), redeem (menukar/belanja), adjust (koreksi/manual)
            $table->enum('type', ['earn', 'redeem', 'adjust']);
            
            $table->integer('points'); // Bisa positif (earn) atau negatif (redeem)
            $table->decimal('amount', 15, 2)->default(0); // Nilai cashback / potongan rupiah
            $table->text('description')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
