<?php

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Master\Customer;
use App\Models\Master\Station;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuspendedCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_code',
        'station_id',
        'user_id',
        'customer_id',
        'total_items',
        'total_amount',
        'status',
        'notes',
    ];

    public function items()
    {
        return $this->hasMany(SuspendedCartItem::class);
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
