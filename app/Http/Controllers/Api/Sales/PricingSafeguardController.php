<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;

use App\Models\Master\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingSafeguardController extends Controller
{
    use ApiResponseTrait;

    /**
     * 1. POST /api/pricing/set-rules
     * Menetapkan Harga Jual & Batas Margin Minimum Aman per Produk
     */
    public function setPriceRules(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'selling_price' => 'required|numeric|min:0',
            'min_margin_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $productId = $request->input('product_id');
        $sellingPrice = (float)$request->input('selling_price');
        $minMarginPercent = (float)$request->input('min_margin_percentage');

        $product = Product::findOrFail($productId);
        $costPrice = (float)$product->cost_price;

        // Formula Ritel Standar Margin Aman:
        // Harga Minimum = Cost Price / (1 - (Margin / 100))
        // Atau Formula Mark-Up (Sederhana):
        // Harga Minimum = Cost Price * (1 + (Margin / 100))
        // Kita gunakan Formula Margin Ritel GAAP: min_price = cost_price / (1 - (min_margin / 100))
        // Jika min_margin_percentage adalah 100%, hindari pembagian nol dengan fallback markup.
        if ($minMarginPercent >= 100.0) {
            $minAllowedPrice = $costPrice * 2.0; // Fallback jika margin diatur 100%
        } else {
            $minAllowedPrice = $costPrice / (1 - ($minMarginPercent / 100.0));
        }

        // Bulatkan ke kelipatan terdekat (misal Rp 100)
        $minAllowedPrice = ceil($minAllowedPrice / 100) * 100;

        if ($sellingPrice < $minAllowedPrice) {
            return $this->errorResponse(
                "Penetapan harga ditolak! Harga jual yang diusulkan (Rp " . number_format($sellingPrice, 0, ',', '.') . 
                ") menghasilkan margin di bawah batas minimum aman yang Anda minta ({$minMarginPercent}%). " .
                "Berdasarkan Harga Modal (Rp " . number_format($costPrice, 0, ',', '.') . "), harga jual minimum yang diizinkan adalah Rp " . number_format($minAllowedPrice, 0, ',', '.'),
                422
            );
        }

        // Simpan harga baru dan batas margin aman ke database
        $product->price = $sellingPrice;
        $product->min_margin_percentage = $minMarginPercent;
        $product->save();

        $actualMarginPercent = (($sellingPrice - $costPrice) / $sellingPrice) * 100;

        return $this->successResponse([
            'product_id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'min_margin_percentage' => $minMarginPercent . '%',
            'actual_margin_percentage' => round($actualMarginPercent, 2) . '%',
            'status' => 'SAFE & APPROVED',
        ], 'Product selling price and margin safeguard rules updated successfully');
    }

    /**
     * 2. POST /api/pricing/validate-promo
     * Menguji & Validasi Apakah Potongan Promosi Menyebabkan Margin Minus / Rugi
     */
    public function validatePromoMargin(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'discount_type' => 'required|in:fixed,percent',
            'discount_value' => 'required|numeric|min:0',
        ]);

        $productId = $request->input('product_id');
        $discountType = $request->input('discount_type');
        $discountValue = (float)$request->input('discount_value');

        $product = Product::findOrFail($productId);
        $costPrice = (float)$product->cost_price;
        $originalPrice = (float)$product->price;
        $minMarginPercent = (float)($product->min_margin_percentage ?? 10.00);

        // 1. Hitung Besaran Diskon & Harga Usulan Setelah Diskon
        $discountAmount = 0.0;
        if ($discountType === 'percent') {
            $discountAmount = $originalPrice * ($discountValue / 100.0);
        } else {
            $discountAmount = $discountValue;
        }

        $proposedPrice = max(0.0, $originalPrice - $discountAmount);
        
        // 2. Hitung Margin Keuntungan Setelah Diskon
        $proposedMarginNominal = $proposedPrice - $costPrice;
        $proposedMarginPercent = $proposedPrice > 0 ? ($proposedMarginNominal / $proposedPrice) * 100.0 : -100.0;

        // 3. Batas Harga Jual Terendah yang Diizinkan (Safeguard margin)
        if ($minMarginPercent >= 100.0) {
            $minAllowedPrice = $costPrice * 2.0;
        } else {
            $minAllowedPrice = $costPrice / (1 - ($minMarginPercent / 100.0));
        }
        $minAllowedPrice = ceil($minAllowedPrice / 100) * 100;

        // 4. Hitung Maksimum Diskon Aman yang Dapat Diberikan
        $maxSafeDiscountAmount = max(0.0, $originalPrice - $minAllowedPrice);
        $maxSafeDiscountPercent = $originalPrice > 0 ? ($maxSafeDiscountAmount / $originalPrice) * 100.0 : 0.0;

        $isSafe = $proposedPrice >= $minAllowedPrice;
        $status = $isSafe ? 'SAFE (Disetujui)' : 'VIOLATED (Ditolak - Margin Minus/Kritis)';

        $response = [
            'product' => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'cost_price' => $costPrice,
                'original_selling_price' => $originalPrice,
                'min_margin_percentage_safeguard' => $minMarginPercent . '%',
            ],
            'proposed_promotion' => [
                'discount_applied' => $discountType === 'percent' ? $discountValue . '%' : 'Rp ' . number_format($discountValue, 0, ',', '.'),
                'discount_amount_nominal' => $discountAmount,
                'proposed_selling_price' => $proposedPrice,
                'margin_nominal_after_discount' => $proposedMarginNominal,
                'margin_percentage_after_discount' => round($proposedMarginPercent, 2) . '%',
            ],
            'safeguard_limits' => [
                'minimum_allowed_price' => $minAllowedPrice,
                'maximum_safe_discount_amount' => $maxSafeDiscountAmount,
                'maximum_safe_discount_percent' => round($maxSafeDiscountPercent, 2) . '%',
            ],
            'validation' => [
                'is_safe_to_apply' => $isSafe,
                'status' => $status,
                'warning_message' => $isSafe ? null : "Diskon terlalu besar! Menurunkan harga jual menjadi Rp " . number_format($proposedPrice, 0, ',', '.') . " yang berada di bawah batas aman modal + margin ritel (Rp " . number_format($minAllowedPrice, 0, ',', '.') . ")."
            ]
        ];

        return $this->successResponse($response, 'Promotion margin safeguard validation completed successfully');
    }
}
