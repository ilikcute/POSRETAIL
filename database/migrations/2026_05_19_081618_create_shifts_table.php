<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('station_id')->constrained('stations')->restrictOnDelete();

            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();

            $table->decimal('starting_cash', 15, 2)->default(0);

            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);

            // Cash breakdown
            $table->decimal('expected_cash', 15, 2)->default(0);
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->decimal('difference_cash', 15, 2)->default(0);

            // QRIS breakdown
            $table->decimal('expected_qris', 15, 2)->default(0);
            $table->decimal('actual_qris', 15, 2)->nullable();
            $table->decimal('difference_qris', 15, 2)->default(0);

            // Card breakdown (debit/kredit)
            $table->decimal('expected_card', 15, 2)->default(0);
            $table->decimal('actual_card', 15, 2)->nullable();
            $table->decimal('difference_card', 15, 2)->default(0);

            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
