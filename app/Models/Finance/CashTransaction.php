<?php

namespace App\Models\Finance;

use App\Models\Auth\User;
use App\Models\Master\Store;
use App\Models\Sales\Shift;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'shift_id',
        'type',
        'amount',
        'category',
        'payment_method',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
