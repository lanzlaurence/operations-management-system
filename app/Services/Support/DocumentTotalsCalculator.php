<?php

namespace App\Services\Support;

use App\Data\DocumentChargeData;
use App\Enums\DiscountType;
use App\Support\Money;

/**
 * Rolls line totals up into document totals.
 *
 * The computation order is fixed and shared by purchase and sales documents:
 *
 *   1. sum the lines                       -> total_net_price / total_vat / total_gross
 *   2. apply the header discount on gross   -> header_discount_total
 *   3. resolve each charge on the discounted gross (percentage charges included)
 *   4. taxes add, discount charges subtract -> total_charges
 *   5. grand_total = discounted gross + total_charges
 */
final class DocumentTotalsCalculator
{
    /**
     * @param  iterable<LineTotals>  $lines
     * @param  array<int, DocumentChargeData>  $charges
     */
    public function compute(
        iterable $lines,
        ?DiscountType $headerDiscountType = null,
        float $headerDiscountAmount = 0.0,
        array $charges = [],
    ): DocumentTotals {
        $totalBeforeDiscount = 0.0;
        $totalItemDiscount = 0.0;
        $totalNet = 0.0;
        $totalVat = 0.0;
        $totalGross = 0.0;

        foreach ($lines as $line) {
            $totalBeforeDiscount += $line->baseAmount();
            $totalItemDiscount += $line->lineDiscountTotal;
            $totalNet += $line->netPrice;
            $totalVat += $line->vatPrice;
            $totalGross += $line->grossPrice;
        }

        $totalGross = Money::round($totalGross);

        $headerDiscountTotal = $headerDiscountType === null
            ? 0.0
            : min($totalGross, Money::positive($headerDiscountType->amountFor($totalGross, $headerDiscountAmount)));

        $discountedGross = Money::round($totalGross - $headerDiscountTotal);

        [$chargeAmounts, $totalCharges] = $this->resolveCharges($charges, $discountedGross);

        return new DocumentTotals(
            totalBeforeDiscount: Money::round($totalBeforeDiscount),
            totalItemDiscount: Money::round($totalItemDiscount),
            totalNetPrice: Money::round($totalNet),
            totalVat: Money::round($totalVat),
            totalGross: $totalGross,
            headerDiscountTotal: Money::round($headerDiscountTotal),
            totalCharges: $totalCharges,
            grandTotal: Money::positive($discountedGross + $totalCharges),
            chargeAmounts: $chargeAmounts,
        );
    }

    /**
     * Resolve every charge against the discounted gross amount.
     *
     * @param  array<int, DocumentChargeData>  $charges
     * @return array{0: array<int, float>, 1: float} [amount per charge, signed total]
     */
    private function resolveCharges(array $charges, float $base): array
    {
        $amounts = [];
        $total = 0.0;

        foreach ($charges as $index => $charge) {
            $amount = Money::positive($charge->valueType->computeOn($base, $charge->value));

            $amounts[$index] = $amount;
            $total += $charge->type->sign() * $amount;
        }

        return [$amounts, Money::round($total)];
    }
}
