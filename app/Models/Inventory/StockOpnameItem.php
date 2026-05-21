<?php

namespace App\Models\Inventory;

use App\Models\Master\Product;
use App\Models\Master\ProductVariant;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'product_variant_id',
        'system_qty',
        'physical_qty',
        'discrepancy',
        'unit_cost',
        'discrepancy_value',
        'notes',
    ];

    protected $casts = [
        'system_qty' => 'decimal:2',
        'physical_qty' => 'decimal:2',
        'discrepancy' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'discrepancy_value' => 'decimal:2',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
