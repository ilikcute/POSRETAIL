<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            // Batas maksimum kas tunai aman di laci kasir stasiun ini (default: Rp 2.000.000)
            $table->decimal('drawer_safety_limit', 15, 2)->default(2000000.00)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn('drawer_safety_limit');
        });
    }
};
