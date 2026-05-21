<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'tax_number',
        'logo_path',
        'header_text',
        'footer_text',
        'print_settings',
        'default_printer_id',
        'default_receipt_template_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'print_settings' => 'array', // otomatis casting JSON ke array
    ];

    // Relasi akan kita tambahkan nanti setelah tabel printer & template dibuat
}
