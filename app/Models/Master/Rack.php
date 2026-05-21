<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rack extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['shelf_level', 'position_order', 'facing', 'max_capacity'])
            ->withTimestamps();
    }
}
