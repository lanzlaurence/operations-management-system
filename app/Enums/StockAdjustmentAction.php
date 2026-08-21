<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * The three operations offered by the manual inventory adjustment screen.
 */
enum StockAdjustmentAction: string
{
    use EnumHelpers;

    case Initial = 'initial';
    case Adjust = 'adjust';
    case Transfer = 'transfer';

    /** The inventory movement written to the log for this action. */
    public function movementType(): InventoryMovementType
    {
        return match ($this) {
            self::Initial => InventoryMovementType::Initial,
            self::Adjust => InventoryMovementType::Adjustment,
            self::Transfer => InventoryMovementType::TransferOut,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial Stock',
            self::Adjust => 'Adjust Quantity',
            self::Transfer => 'Transfer Between Locations',
        };
    }
}
