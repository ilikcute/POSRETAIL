<?php

namespace App\Models\Master;

use App\Models\Inventory\ProductStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'unit_id',
        'code',
        'name',
        'sku',
        'barcode',
        'cost_price',
        'price',
        'wholesale_price',
        'description',
        'image_path',
        'safety_stock',
        'reorder_point',
        'lead_time',
        'is_taxable',
        'is_consignment',
        'is_active',
        'purchase_type',
        'consignment_commission_fee',
        'min_margin_percentage',
    ];

    protected $casts = [
        'safety_stock' => 'decimal:2',
        'reorder_point' => 'decimal:2',
        'lead_time' => 'integer',
        'is_taxable' => 'boolean',
        'is_consignment' => 'boolean',
        'is_active' => 'boolean',
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'consignment_commission_fee' => 'decimal:2',
        'min_margin_percentage' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function racks()
    {
        return $this->belongsToMany(Rack::class)
            ->withPivot(['shelf_level', 'position_order', 'facing', 'max_capacity'])
            ->withTimestamps();
    }

    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }
}
