<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Whether a line amount already contains VAT (inclusive) or VAT has to be
 * added on top of it (exclusive).
 */
enum VatType: string
{
    use EnumHelpers;

    case Exclusive = 'exclusive';
    case Inclusive = 'inclusive';

    public function isInclusive(): bool
    {
        return $this === self::Inclusive;
    }

    public function label(): string
    {
        return match ($this) {
            self::Exclusive => 'VAT Exclusive',
            self::Inclusive => 'VAT Inclusive',
        };
    }
}
