<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Tambahkan status pembayaran (unpaid = piutang penuh, partial = piutang sebagian, paid = lunas)
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('paid')->after('status');
            // Tanggal jatuh tempo pembayaran piutang
            $table->date('due_date')->nullable()->after('payment_status');
        });

        Schema::table('purchases', function (Blueprint $table) {
            // Nominal yang sudah dibayar ke supplier
            $table->decimal('amount_paid', 15, 2)->default(0.00)->after('payment_status');
            // Tanggal jatuh tempo pembayaran utang
            $table->date('due_date')->nullable()->after('amount_paid');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'due_date']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'due_date']);
        });
    }
};
