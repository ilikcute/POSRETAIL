<?php

namespace App\Models\Inventory;

use App\Models\Master\Warehouse;
use App\Models\Master\Rack;
use App\Models\Master\Product;
use App\Models\Master\ProductVariant;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'rack_id',
        'qty',
        'min_qty',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'min_qty' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }
}
