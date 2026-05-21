<?php

namespace App\Models\Inventory;

use App\Models\Master\Product;
use App\Models\Master\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockDisposalItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_disposal_id',
        'product_id',
        'product_variant_id',
        'qty',
        'unit_cost',
        'subtotal',
        'notes',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function stockDisposal()
    {
        return $this->belongsTo(StockDisposal::class);
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
