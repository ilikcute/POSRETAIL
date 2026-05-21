<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_rack', function (Blueprint $table) {
            $table->integer('shelf_level')->default(1)->after('rack_id'); // Tingkat rak (1 = paling bawah, 2, 3, dst.)
            $table->integer('position_order')->default(1)->after('shelf_level'); // Urutan posisi dari kiri ke kanan
            $table->integer('facing')->default(1)->after('position_order'); // Jumlah barang menghadap ke depan
            $table->integer('max_capacity')->default(10)->after('facing'); // Kapasitas pajangan maksimal untuk produk ini di rak ini
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_rack', function (Blueprint $table) {
            $table->dropColumn(['shelf_level', 'position_order', 'facing', 'max_capacity']);
        });
    }
};
