<?php

namespace App\Models\Finance;

use App\Models\Auth\User;
use App\Models\Master\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthEnd extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'month',
        'year',
        'total_sales',
        'total_cost_of_goods_sold',
        'total_purchases',
        'gross_profit',
        'closed_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'total_sales' => 'decimal:2',
        'total_cost_of_goods_sold' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'gross_profit' => 'decimal:2',
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
