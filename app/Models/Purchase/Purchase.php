<?php

namespace App\Models\Purchase;

use App\Models\Auth\User;
use App\Models\Master\Store;
use App\Models\Master\Warehouse;
use App\Models\Master\Supplier;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'supplier_id',
        'warehouse_id',
        'created_by',
        'reference_no',
        'type',
        'status',
        'payment_status',
        'total_items',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'shipping_cost',
        'grand_total',
        'notes',
        'parent_id',
    ];

    protected $casts = [
        'total_items' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent()
    {
        return $this->belongsTo(Purchase::class, 'parent_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
