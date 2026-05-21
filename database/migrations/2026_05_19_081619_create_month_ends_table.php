<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('month_ends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            
            $table->integer('month');
            $table->integer('year');
            
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_cost_of_goods_sold', 15, 2)->default(0); // HPP
            $table->decimal('total_purchases', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->default(0); // Penjualan - HPP
            
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('closed_at');
            $table->text('notes')->nullable();
            
            $table->unique(['store_id', 'month', 'year']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('month_ends');
    }
};
