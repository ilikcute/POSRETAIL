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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('header_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->json('print_settings')->nullable();

            // Kolom foreign key dibuat nullable dulu karena master printernya mungkin belum ada saat toko dibuat
            $table->foreignId('default_printer_id')->nullable();
            $table->foreignId('default_receipt_template_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
