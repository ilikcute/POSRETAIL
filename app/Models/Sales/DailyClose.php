<?php

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Master\Store;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyClose extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'close_date',
        'total_sales',
        'total_purchases',
        'total_cash_sales',
        'total_non_cash_sales',
        'total_shift_difference',
        'closed_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'close_date' => 'date',
        'total_sales' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'total_cash_sales' => 'decimal:2',
        'total_non_cash_sales' => 'decimal:2',
        'total_shift_difference' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
