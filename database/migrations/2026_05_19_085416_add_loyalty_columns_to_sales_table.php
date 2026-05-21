<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->integer('points_earned')->default(0)->after('grand_total');
            $table->integer('points_redeemed')->default(0)->after('points_earned');
            $table->decimal('points_discount', 15, 2)->default(0)->after('points_redeemed');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['points_earned', 'points_redeemed', 'points_discount']);
        });
    }
};
