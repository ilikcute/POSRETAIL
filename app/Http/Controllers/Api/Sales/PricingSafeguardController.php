<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\PricingSafeguardViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SetPriceRulesRequest;
use App\Http\Requests\Sales\ValidatePromoMarginRequest;
use App\Models\Master\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PricingSafeguardController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/pricing/safeguards
     * Ringkasan produk untuk modul smart pricing safeguard.
     */
    public function index(): JsonResponse
    {
        $products = Product::query()
            ->select([
                'id',
                'code',
                'name',
                'sku',
                'barcode',
                'cost_price',
                'price',
                'min_margin_percentage',
                'is_active',
                'updated_at',
            ])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => $this->productPayload($product));

        return $this->successResponse($products, 'Pricing safeguard products retrieved successfully');
    }

    /**
     * POST /api/pricing/set-rules
     * Menetapkan harga jual dan batas margin minimum aman per produk.
     */
    public function setPriceRules(SetPriceRulesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $productId = (int) $validated['product_id'];
        $sellingPrice = (float) $validated['selling_price'];
        $minMarginPercent = (float) $validated['min_margin_percentage'];

        try {
            $result = DB::transaction(function () use ($productId, $sellingPrice, $minMarginPercent): array {
                /** @var Product $product */
                $product = Product::query()
                    ->whereKey($productId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $costPrice = (float) $product->cost_price;
                $minAllowedPrice = $this->minimumAllowedPrice($costPrice, $minMarginPercent);

                if ($sellingPrice < $minAllowedPrice) {
                    throw new PricingSafeguardViolationException(
                        'Penetapan harga ditolak. Harga jual berada di bawah batas margin aman.',
                        [
                            'minimum_allowed_price' => $minAllowedPrice,
                            'proposed_selling_price' => $sellingPrice,
                            'cost_price' => $costPrice,
                            'min_margin_percentage' => $minMarginPercent,
                        ]
                    );
                }

                $product->forceFill([
                    'price' => $sellingPrice,
                    'min_margin_percentage' => $minMarginPercent,
                ])->save();

                $product->refresh();

                return [
                    'product' => $this->productPayload($product),
                    'rule' => [
                        'minimum_allowed_price' => $minAllowedPrice,
                        'actual_margin_percentage' => $this->marginPercentage($sellingPrice, $costPrice),
                        'status' => 'SAFE_AND_APPROVED',
                        'status_label' => 'Harga aman dan disetujui',
                    ],
                ];
            });

            return $this->successResponse($result, 'Product selling price and margin safeguard rules updated successfully');
        } catch (PricingSafeguardViolationException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'selling_price' => [
                    $this->pricingViolationMessage($e->contextData()),
                ],
                'safeguard' => $e->contextData(),
            ]);
        } catch (\Throwable $e) {
            Log::error('PricingSafeguardController::setPriceRules failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Terjadi kesalahan internal saat menyimpan aturan pricing safeguard.', 500);
        }
    }

    /**
     * POST /api/pricing/validate-promo
     * Menguji apakah potongan promosi membuat margin turun melewati batas aman.
     */
    public function validatePromoMargin(ValidatePromoMarginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var Product $product */
        $product = Product::query()
            ->select(['id', 'code', 'name', 'sku', 'barcode', 'cost_price', 'price', 'min_margin_percentage', 'is_active'])
            ->findOrFail((int) $validated['product_id']);

        $discountType = $validated['discount_type'];
        $discountValue = (float) $validated['discount_value'];
        $costPrice = (float) $product->cost_price;
        $originalPrice = (float) $product->price;
        $minMarginPercent = (float) ($product->min_margin_percentage ?? 10.00);

        $discountAmount = $discountType === 'percent'
            ? $originalPrice * ($discountValue / 100.0)
            : $discountValue;

        $proposedPrice = max(0.0, $originalPrice - $discountAmount);
        $minAllowedPrice = $this->minimumAllowedPrice($costPrice, $minMarginPercent);
        $maxSafeDiscountAmount = max(0.0, $originalPrice - $minAllowedPrice);
        $maxSafeDiscountPercent = $originalPrice > 0
            ? round(($maxSafeDiscountAmount / $originalPrice) * 100.0, 2)
            : 0.0;

        $isSafe = $proposedPrice >= $minAllowedPrice;

        return $this->successResponse([
            'product' => $this->productPayload($product),
            'proposed_promotion' => [
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount_nominal' => round($discountAmount, 2),
                'proposed_selling_price' => round($proposedPrice, 2),
                'margin_nominal_after_discount' => round($proposedPrice - $costPrice, 2),
                'margin_percentage_after_discount' => $this->marginPercentage($proposedPrice, $costPrice),
            ],
            'safeguard_limits' => [
                'minimum_allowed_price' => $minAllowedPrice,
                'maximum_safe_discount_amount' => round($maxSafeDiscountAmount, 2),
                'maximum_safe_discount_percent' => $maxSafeDiscountPercent,
            ],
            'validation' => [
                'is_safe_to_apply' => $isSafe,
                'status' => $isSafe ? 'SAFE' : 'VIOLATED',
                'status_label' => $isSafe ? 'Aman untuk diterapkan' : 'Ditolak karena margin kritis',
                'warning_message' => $isSafe
                    ? null
                    : 'Diskon terlalu besar. Harga setelah diskon berada di bawah batas aman modal dan margin ritel.',
            ],
        ], 'Promotion margin safeguard validation completed successfully');
    }

    private function productPayload(Product $product): array
    {
        $costPrice = (float) $product->cost_price;
        $price = (float) $product->price;
        $minMargin = (float) ($product->min_margin_percentage ?? 10.00);

        return [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'cost_price' => $costPrice,
            'selling_price' => $price,
            'price' => $price,
            'min_margin_percentage' => $minMargin,
            'minimum_allowed_price' => $this->minimumAllowedPrice($costPrice, $minMargin),
            'actual_margin_percentage' => $this->marginPercentage($price, $costPrice),
            'is_active' => (bool) $product->is_active,
            'updated_at' => $product->updated_at,
        ];
    }

    private function minimumAllowedPrice(float $costPrice, float $minMarginPercent): float
    {
        if ($costPrice <= 0.0) {
            return 0.0;
        }

        $margin = min(99.99, max(0.0, $minMarginPercent));
        $minimumPrice = $costPrice / (1 - ($margin / 100.0));

        return (float) (ceil($minimumPrice / 100) * 100);
    }

    private function marginPercentage(float $sellingPrice, float $costPrice): float
    {
        if ($sellingPrice <= 0.0) {
            return $costPrice > 0.0 ? -100.0 : 0.0;
        }

        return round((($sellingPrice - $costPrice) / $sellingPrice) * 100.0, 2);
    }

    private function pricingViolationMessage(array $context): string
    {
        return 'Harga jual minimum yang diizinkan adalah Rp '.
            number_format((float) ($context['minimum_allowed_price'] ?? 0), 0, ',', '.').
            ' berdasarkan harga modal Rp '.
            number_format((float) ($context['cost_price'] ?? 0), 0, ',', '.').
            ' dan safeguard margin '.
            number_format((float) ($context['min_margin_percentage'] ?? 0), 2, ',', '.').'%.';
    }
}
