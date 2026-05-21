<?php

namespace App\Models\Sales;

use App\Models\Master\Product;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuspendedCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'suspended_cart_id',
        'product_id',
        'qty',
        'unit_price',
        'subtotal',
        'notes',
    ];

    public function cart()
    {
        return $this->belongsTo(SuspendedCart::class, 'suspended_cart_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
