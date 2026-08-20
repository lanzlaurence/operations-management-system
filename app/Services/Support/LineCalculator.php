<?php

namespace App\Services\Support;

use App\Enums\DiscountType;
use App\Enums\VatType;
use App\Support\Money;

/**
 * Single source of truth for line-level pricing.
 *
 * The rules it implements:
 *
 *  1. The line discount is applied to the unit amount first, so the stored
 *     `unit_*_after_discount` is what receiving and issuing later cost at.
 *  2. VAT exclusive lines: net = qty x discounted unit, VAT is added on top.
 *  3. VAT inclusive lines: qty x discounted unit already contains the VAT, so
 *     the tax is extracted out of it and `net_price` stays VAT free.
 *
 * That last rule is what makes `total_net_price + total_vat = total_gross`
 * hold for every combination of lines on a document.
 */
final class LineCalculator
{
    /** Fallback VAT rate when a vatable line does not specify one. */
    public const DEFAULT_VAT_RATE = 12.0;

    public function calculate(
        float $quantity,
        float $unitAmount,
        ?DiscountType $discountType = null,
        float $discountAmount = 0.0,
        bool $isVatable = false,
        ?VatType $vatType = null,
        ?float $vatRate = null,
    ): LineTotals {
        $quantity = Money::quantity(max(0, $quantity));
        $unitAmount = Money::positive($unitAmount);
        $discountAmount = $discountType === null ? 0.0 : Money::positive($discountAmount);

        $unitAfterDiscount = $discountType === null
            ? $unitAmount
            : Money::positive($discountType->applyTo($unitAmount, $discountAmount));

        $lineAmount = Money::round($quantity * $unitAfterDiscount);
        $lineDiscountTotal = Money::round($quantity * ($unitAmount - $unitAfterDiscount));

        // A non-vatable line carries no VAT type or rate at all.
        $vatType = $isVatable ? ($vatType ?? VatType::Exclusive) : null;
        $vatRate = $isVatable ? Money::round($vatRate ?? self::DEFAULT_VAT_RATE) : 0.0;

        [$netPrice, $vatPrice] = $this->splitVat($lineAmount, $isVatable, $vatType, $vatRate);

        return new LineTotals(
            quantity: $quantity,
            unitAmount: $unitAmount,
            discountType: $discountType,
            discountAmount: $discountAmount,
            unitAmountAfterDiscount: $unitAfterDiscount,
            lineDiscountTotal: $lineDiscountTotal,
            isVatable: $isVatable,
            vatType: $vatType,
            vatRate: $vatRate,
            netPrice: $netPrice,
            vatPrice: $vatPrice,
            grossPrice: Money::round($netPrice + $vatPrice),
        );
    }

    /**
     * Split a discounted line amount into its net and VAT parts.
     *
     * @return array{0: float, 1: float} [net, vat]
     */
    private function splitVat(float $lineAmount, bool $isVatable, ?VatType $vatType, float $vatRate): array
    {
        if (! $isVatable || $vatRate <= 0) {
            return [$lineAmount, 0.0];
        }

        if ($vatType?->isInclusive()) {
            $net = Money::round($lineAmount / (1 + ($vatRate / 100)));

            return [$net, Money::round($lineAmount - $net)];
        }

        return [$lineAmount, Money::round($lineAmount * ($vatRate / 100))];
    }
}
