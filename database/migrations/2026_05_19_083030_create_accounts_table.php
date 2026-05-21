<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 1101, 4101
            $table->string('name');
            
            // Tipe Akun: asset, liability, equity, revenue, expense
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            
            $table->decimal('balance', 15, 2)->default(0); // Saldo saat ini
            $table->text('description')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
