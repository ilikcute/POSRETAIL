<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Station extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ip_address',
        'location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
