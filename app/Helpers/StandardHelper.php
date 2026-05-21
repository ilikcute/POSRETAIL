<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class StandardHelper
{
    /**
     * Generate a unique transaction/invoice number
     *
     * @param string $prefix
     * @return string
     */
    public static function generateTransactionNumber(string $prefix = 'INV'): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(5));
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Format number to IDR currency
     *
     * @param float $amount
     * @return string
     */
    public static function formatRupiah(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
