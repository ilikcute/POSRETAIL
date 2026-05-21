<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();

            // Tipe: in (Uang Masuk / Revenue Lain), out (Uang Keluar / Beban Pengeluaran)
            $table->enum('type', ['in', 'out']);

            $table->decimal('amount', 15, 2);
            $table->string('category'); // e.g., operasional, penjualan_kardus, listrik, atk, dll.
            $table->string('payment_method')->default('cash'); // Biasanya cash/petty cash

            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
