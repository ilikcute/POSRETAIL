<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'balance',
        'description',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * List of critical system accounts that cannot be deleted or have their type/code altered.
     */
    public static array $protectedCodes = [
        '1101', // Kas Toko / Petty Cash
        '1102', // Bank Mandiri Toko
        '1103', // Piutang Dagang / Debt Ledger
        '1104', // PPN Masukan
        '1201', // Persediaan Barang Dagang
        '2101', // Hutang Dagang
        '2102', // Hutang Konsinyasi
        '2201', // PPN Keluaran
        '3101', // Modal Disetor
        '4101', // Pendapatan Penjualan Retail
        '4201', // Pendapatan Lain-lain
        '5101', // Harga Pokok Penjualan (HPP)
        '5201', // Beban Listrik Toko
        '5202', // Beban Kerusakan & Selisih Persediaan
    ];

    /**
     * Check if this account is a system-critical account.
     */
    public function isSystemAccount(): bool
    {
        return in_array($this->code, self::$protectedCodes);
    }

    /**
     * Relation to journal entry items.
     */
    public function journalItems()
    {
        return $this->hasMany(JournalItem::class);
    }
}
