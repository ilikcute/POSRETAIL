<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Store;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mengingat requirement aplikasi ini adalah "Single Tenant, Multi Station",
        // maka idealnya hanya ada 1 Store utama yang terdaftar.
        
        Store::create([
            'name' => 'POS Retail Utama',
            'address' => 'Jl. Jendral Sudirman No. 1, Jakarta Pusat, DKI Jakarta',
            'phone' => '021-5551234',
            'email' => 'contact@posretail.com',
            'tax_number' => '01.234.567.8-091.000',
            'header_text' => '--- POS RETAIL UTAMA ---',
            'footer_text' => 'Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.',
            'print_settings' => [
                'paper_width' => 80,
                'print_barcode' => true,
                'print_qrcode' => false
            ],
            'is_active' => true,
        ]);

        // Jika Anda ingin men-generate beberapa data dummy (meski single tenant) 
        // untuk keperluan testing UI, bisa uncomment kode di bawah ini:
        // Store::factory(2)->create();
    }
}
