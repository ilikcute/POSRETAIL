<?php

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Master\Station;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'station_id',
        'start_time',
        'end_time',
        'starting_cash',
        'total_sales',
        'total_discount',
        'expected_cash',
        'actual_cash',
        'difference_cash',
        'expected_qris',
        'actual_qris',
        'difference_qris',
        'expected_card',
        'actual_card',
        'difference_card',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'starting_cash' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'difference_cash' => 'decimal:2',
        'expected_qris' => 'decimal:2',
        'actual_qris' => 'decimal:2',
        'difference_qris' => 'decimal:2',
        'expected_card' => 'decimal:2',
        'actual_card' => 'decimal:2',
        'difference_card' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
