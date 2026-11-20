<?php

namespace App\Support;

use App\Models\Tax;

class SalePricing
{
    /**
     * Split a tax-inclusive gross amount into base + tax.
     *
     * @return array{base: float, tax: float}
     */
    public static function splitGross(float $gross, float $taxRate): array
    {
        $gross = round(max(0, $gross), 2);
        $rate = max(0, $taxRate) / 100;

        if ($gross <= 0 || $rate <= 0) {
            return ['base' => $gross, 'tax' => 0.0];
        }

        $base = round($gross / (1 + $rate), 2);
        $tax = round($gross - $base, 2);

        return ['base' => $base, 'tax' => $tax];
    }

    /**
     * Allocate a global discount across lines by gross weight.
     *
     * @param  array<int, float>  $lineGrosses
     * @return array<int, float>
     */
    public static function allocateDiscount(array $lineGrosses, float $discountAmount): array
    {
        $total = round(array_sum($lineGrosses), 2);
        $discountAmount = round(max(0, min($discountAmount, $total)), 2);
        $count = count($lineGrosses);
        if ($count === 0 || $total <= 0 || $discountAmount <= 0) {
            return array_fill(0, $count, 0.0);
        }

        $allocated = [];
        $assigned = 0.0;
        $keys = array_keys($lineGrosses);

        foreach ($keys as $index => $key) {
            $isLast = $index === $count - 1;
            if ($isLast) {
                $share = round($discountAmount - $assigned, 2);
            } else {
                $share = round(($lineGrosses[$key] / $total) * $discountAmount, 2);
                $assigned = round($assigned + $share, 2);
            }
            $allocated[$key] = max(0, min($share, round($lineGrosses[$key], 2)));
        }

        return $allocated;
    }

    public static function defaultTax(): Tax
    {
        $tax = Tax::query()->active()->where('code', 'IVA_19')->first()
            ?: Tax::query()->active()->orderBy('sort_order')->orderBy('id')->first();

        if (!$tax) {
            throw new \RuntimeException('No hay impuestos configurados.');
        }

        return $tax;
    }

    public static function resolveTax(?int $taxId = null): Tax
    {
        if ($taxId) {
            $tax = Tax::query()->active()->find($taxId);
            if ($tax) {
                return $tax;
            }
        }

        return self::defaultTax();
    }
}
