<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Tipe promo: percentage (diskon 20%), fixed_amount (potongan Rp 50.000)
            $table->enum('type', ['percentage', 'fixed_amount'])->default('percentage');

            $table->decimal('value', 15, 2); // 20 untuk 20%, atau 50000 untuk Rp 50.000
            $table->decimal('min_purchase_amount', 15, 2)->default(0); // Syarat belanja minimum
            $table->decimal('max_discount_amount', 15, 2)->nullable(); // Maksimal diskon (untuk persentase)

            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
