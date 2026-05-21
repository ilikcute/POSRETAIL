<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Batas margin keuntungan minimum aman dalam persen (default: 10.00%)
            $table->decimal('min_margin_percentage', 5, 2)->default(10.00)->after('consignment_commission_fee');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('min_margin_percentage');
        });
    }
};
