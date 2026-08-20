<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Every reason a stock quantity can change. The value list must stay in sync
 * with the enum column on `inventory_logs`.
 */
enum InventoryMovementType: string
{
    use EnumHelpers;

    // Inventory module
    case Initial = 'initial';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    // Purchasing module
    case PurchaseReceipt = 'purchase_receipt';
    case PurchaseReturn = 'purchase_return';

    // Sales module
    case SalesIssue = 'sales_issue';
    case SalesReturn = 'sales_return';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial Stock',
            self::Adjustment => 'Manual Adjustment',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::PurchaseReceipt => 'Purchase Receipt',
            self::PurchaseReturn => 'Purchase Return',
            self::SalesIssue => 'Sales Issue',
            self::SalesReturn => 'Sales Return',
        };
    }

    /** Movements that add stock to a location. */
    public function isInbound(): bool
    {
        return in_array($this, [
            self::Initial,
            self::TransferIn,
            self::PurchaseReceipt,
            self::SalesReturn,
        ], true);
    }

    /** Movements that remove stock from a location. */
    public function isOutbound(): bool
    {
        return in_array($this, [
            self::TransferOut,
            self::PurchaseReturn,
            self::SalesIssue,
        ], true);
    }

    /** Which functional module the movement originates from. */
    public function module(): string
    {
        return match ($this) {
            self::Initial, self::Adjustment, self::TransferIn, self::TransferOut => 'Inventory',
            self::PurchaseReceipt, self::PurchaseReturn => 'Purchasing',
            self::SalesIssue, self::SalesReturn => 'Sales',
        };
    }
}
