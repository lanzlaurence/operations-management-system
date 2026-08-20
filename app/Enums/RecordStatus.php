<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Activation status shared by master data records (materials, vendors,
 * customers, charges, ...). Only active records may be used on documents.
 */
enum RecordStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
