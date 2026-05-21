<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'member_code',
        'point_balance',
        'is_active',
    ];

    protected $casts = [
        'point_balance' => 'integer',
        'is_active' => 'boolean',
    ];
}
