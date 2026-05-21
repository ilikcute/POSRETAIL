<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            
            $table->string('reference_no')->unique(); // Contoh: SD-202605-0001
            $table->date('disposal_date');
            
            // Alasan pemusnahan: rusak, kedaluwarsa, hama, dll.
            $table->string('reason'); 
            
            // Status: draft, approved, cancelled
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_disposals');
    }
};
