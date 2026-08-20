<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * How a charge value is expressed: a flat amount or a percentage of the
 * document base amount.
 */
enum ChargeValueType: string
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Percentage = 'percentage';

    /**
     * Resolve the charge amount against the document base amount.
     */
    public function computeOn(float $base, float $value): float
    {
        return match ($this) {
            self::Fixed => $value,
            self::Percentage => $base * ($value / 100),
        };
    }
}
