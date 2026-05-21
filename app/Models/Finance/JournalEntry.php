<?php

namespace App\Models\Finance;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'transaction_date',
        'description',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(JournalItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
