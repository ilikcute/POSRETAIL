<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            
            $table->string('reference_no')->unique(); // Contoh: PO-2026-001 atau PI-2026-001
            
            // Tipe dokumen: order (Rencana), purchase (Pembelian Masuk), return (Retur)
            $table->enum('type', ['order', 'purchase', 'return'])->default('purchase');
            
            // Status pengiriman barang
            $table->enum('status', ['pending', 'ordered', 'received', 'completed', 'cancelled'])->default('pending');
            
            // Status pembayaran keuangan
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            
            $table->decimal('total_items', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            
            $table->text('notes')->nullable();
            
            // Referensi ke dokumen lain (misal jika ini Penerimaan dari sebuah PO)
            $table->foreignId('parent_id')->nullable()->constrained('purchases')->nullOnDelete();
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
