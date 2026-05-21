<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'cost_price',
        'price',
        'wholesale_price',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
