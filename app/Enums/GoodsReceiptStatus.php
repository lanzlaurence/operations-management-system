<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Lifecycle of a goods receipt. Only a completed receipt has moved stock,
 * which is what makes cancelling it a reversing inventory movement.
 *
 * pending -> completed -> cancelled -> pending (revert)
 */
enum GoodsReceiptStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    /** True when this status means stock is currently booked in. */
    public function affectsStock(): bool
    {
        return $this === self::Completed;
    }

    public function allowsEditing(): bool
    {
        return $this === self::Pending;
    }

    public function allowsDeletion(): bool
    {
        return $this === self::Pending;
    }

    public function allowsCompletion(): bool
    {
        return $this === self::Pending;
    }

    public function allowsCancellation(): bool
    {
        return in_array($this, [self::Pending, self::Completed], true);
    }

    public function allowsRevert(): bool
    {
        return $this === self::Cancelled;
    }
}
