<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Nature of an additional document charge: it either increases the payable
 * amount (tax/fee) or decreases it (discount/rebate).
 */
enum ChargeType: string
{
    use EnumHelpers;

    case Tax = 'tax';
    case Discount = 'discount';

    /**
     * Sign applied to the computed charge amount when building the grand total.
     */
    public function sign(): int
    {
        return $this === self::Tax ? 1 : -1;
    }
}
