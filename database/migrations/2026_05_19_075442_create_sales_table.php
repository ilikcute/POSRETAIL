<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            
            $table->string('invoice_no')->unique(); // Contoh: INV-2026-0001
            
            // Status: pending (Hold Cart), completed (Paid), void (Cancelled/Refunded)
            $table->enum('status', ['pending', 'completed', 'void'])->default('completed');
            
            // Payment method (cash, card, e-wallet, etc)
            $table->string('payment_method')->nullable();
            
            $table->decimal('total_items', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);
            
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
