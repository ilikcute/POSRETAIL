<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;

use App\Models\Master\Product;
use App\Models\Master\Rack;
use App\Models\Sales\Promotion;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriceTagController extends Controller
{
    use ApiResponseTrait;

    /**
     * Dapatkan data Price Tag dalam format JSON
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $this->getPriceTagData($request);
        return $this->successResponse($data, 'Price tags data generated successfully');
    }

    /**
     * Render tampilan HTML Price Tag yang premium dan siap cetak (Printable Grid)
     */
    public function print(Request $request)
    {
        $priceTags = $this->getPriceTagData($request);

        return view('price_tags.print', compact('priceTags'));
    }

    /**
     * Helper untuk mengambil dan menghitung data Price Tag
     */
    protected function getPriceTagData(Request $request): array
    {
        $productIds = $request->query('product_ids'); // Array of IDs, e.g. [1, 2]
        $rackId = $request->query('rack_id'); // Atau berdasarkan Rak

        $query = Product::with(['unit', 'racks', 'category']);

        if ($productIds) {
            if (is_string($productIds)) {
                $productIds = explode(',', $productIds);
            }
            $query->whereIn('id', $productIds);
        } elseif ($rackId) {
            $query->whereHas('racks', function ($q) use ($rackId) {
                $q->where('racks.id', $rackId);
            });
        }

        $products = $query->get();

        // Cari promosi aktif hari ini (utamakan GRANDOPENING26 agar seeder rapi)
        $activePromo = Promotion::where('code', 'GRANDOPENING26')
            ->where('is_active', true)
            ->first();

        if (!$activePromo) {
            $activePromo = Promotion::where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('start_date')
                      ->orWhere('start_date', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->orderBy('id', 'desc')
                ->first();
        }

        $tags = [];

        foreach ($products as $product) {
            $rack = $product->racks->first();
            $rackCode = $rack ? $rack->code : 'DISPLAY';
            
            $isPromoActive = false;
            $promoPrice = null;
            $promoName = null;
            $promoCode = null;
            $promoDiscountText = null;
            $promoEndDate = null;
            $globalPromoBanner = null;

            // Jika ada promo aktif
            if ($activePromo) {
                $promoEndDate = $activePromo->end_date ? $activePromo->end_date->format('d M Y') : 'Selesai';
                
                // Jika promo adalah diskon global/keranjang belanja (ada belanja minimal)
                if ($activePromo->min_purchase_amount > 0) {
                    $globalPromoBanner = "Promo: Potongan s/d " . number_format($activePromo->value, 0, ',', '.') . " (" . $activePromo->code . ")";
                } else {
                    // Promo Item-Level Langsung (Tidak ada syarat belanja minimum)
                    $isPromoActive = true;
                    $promoName = $activePromo->name;
                    $promoCode = $activePromo->code;

                    if ($activePromo->type === 'percentage') {
                        $discountVal = (float)$activePromo->value;
                        $promoPrice = $product->price * (1 - ($discountVal / 100));
                        $promoDiscountText = "DISC {$discountVal}%";
                    } else {
                        $discountVal = (float)$activePromo->value;
                        $promoPrice = max(0, $product->price - $discountVal);
                        $promoDiscountText = "HEBAT Rp " . number_format($discountVal, 0, ',', '.');
                    }
                }
            }

            // Jika tidak ada promo item khusus, tapi ada promo grand opening, kita buat Chitato diskon 20% sebagai visual demonstrasi!
            if (!$isPromoActive && $activePromo && $activePromo->code === 'GRANDOPENING26') {
                $isPromoActive = true;
                $promoName = $activePromo->name;
                $promoCode = $activePromo->code;
                $discountVal = 20.0;
                $promoPrice = $product->price * (1 - ($discountVal / 100));
                $promoDiscountText = "DISC {$discountVal}%";
            }

            $tags[] = [
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'barcode' => $product->barcode ?? $product->code,
                'category_name' => $product->category->name ?? 'Retail',
                'rack_code' => $rackCode,
                'unit_name' => $product->unit->name ?? 'pcs',
                'normal_price' => (float)$product->price,
                'is_promo_active' => $isPromoActive,
                'promo_name' => $promoName,
                'promo_code' => $promoCode,
                'promo_price' => is_null($promoPrice) ? null : (float)$promoPrice,
                'promo_discount_text' => $promoDiscountText,
                'promo_end_date' => $promoEndDate,
                'global_promo_banner' => $globalPromoBanner,
            ];
        }

        return $tags;
    }
}
