<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Actions recorded on master-data logs (materials, vendors, customers).
 * Unlike DocumentAction these records carry a field-level `changes` diff
 * instead of a status transition.
 */
enum EntityLogAction: string
{
    use EnumHelpers;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
}
