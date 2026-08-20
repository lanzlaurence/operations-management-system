<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Actions recorded on the transaction log (the audit trail of purchase
 * orders, sales orders, goods receipts and goods issues).
 *
 * The string values are part of the API consumed by the frontend badges on
 * `activity/transaction-log`, so they must not be renamed lightly.
 */
enum DocumentAction: string
{
    use EnumHelpers;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Posted = 'posted';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Reverted = 'reverted';
    case ReceiptCreated = 'gr_created';
    case ReceiptDeleted = 'gr_deleted';
    case IssueCreated = 'gi_created';
    case IssueDeleted = 'gi_deleted';
    case StatusRecalculated = 'status_recalculated';

    public function label(): string
    {
        return match ($this) {
            self::ReceiptCreated => 'Goods Receipt Created',
            self::ReceiptDeleted => 'Goods Receipt Deleted',
            self::IssueCreated => 'Goods Issue Created',
            self::IssueDeleted => 'Goods Issue Deleted',
            self::StatusRecalculated => 'Status Recalculated',
            default => str($this->name)->headline()->value(),
        };
    }

    /**
     * Actions that only mirror a derived state change. They are useful for
     * tracing but noisy, so screens may choose to collapse them.
     */
    public function isSystemGenerated(): bool
    {
        return $this === self::StatusRecalculated;
    }
}
