<?php

namespace App\Models\Inventory;

use App\Models\Auth\User;
use App\Models\Master\Warehouse;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockDisposal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'reference_no',
        'disposal_date',
        'reason',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(StockDisposalItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
