<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * How a discount amount should be interpreted, both on document lines and
 * on the document header.
 */
enum DiscountType: string
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Percentage = 'percentage';

    /**
     * Apply the discount to a base amount and return the discounted amount.
     */
    public function applyTo(float $amount, float $discount): float
    {
        return match ($this) {
            self::Fixed => max(0, $amount - $discount),
            self::Percentage => $amount * (1 - (min(100, max(0, $discount)) / 100)),
        };
    }

    /**
     * The monetary value that the discount removes from a base amount.
     */
    public function amountFor(float $amount, float $discount): float
    {
        return max(0, $amount - $this->applyTo($amount, $discount));
    }
}
