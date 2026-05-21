<?php

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Master\Customer;
use App\Models\Master\Station;
use App\Models\Master\Store;
use App\Models\Master\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'station_id',
        'shift_id',
        'warehouse_id',
        'customer_id',
        'promotion_id',
        'created_by',
        'invoice_no',
        'status',
        'payment_method',
        'total_items',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'grand_total',
        'amount_paid',
        'change_amount',
        'notes',
    ];

    protected $casts = [
        'total_items' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
