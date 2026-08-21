<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Lifecycle of a purchase order.
 *
 *   draft --post--> posted --receipts--> partially_received --> fully_received
 *     ^               |                        |                      |
 *     +---- revert ---+                        |                      |
 *     |                                        v                      v
 *     +------------------ revert ------- cancelled <-------------------+
 *
 * The receiving statuses are never set by hand: they are derived from the
 * completed goods receipts by PurchaseOrderService::syncReceiptState().
 */
enum PurchaseOrderStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Posted = 'posted';
    case PartiallyReceived = 'partially_received';
    case FullyReceived = 'fully_received';
    case Cancelled = 'cancelled';

    /** Statuses with receiving still outstanding. */
    public const OPEN = [self::Posted, self::PartiallyReceived];

    /** Statuses that count towards purchasing figures (draft excluded). */
    public const LIVE = [self::Posted, self::PartiallyReceived, self::FullyReceived];

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return array_column(self::OPEN, 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function liveValues(): array
    {
        return array_column(self::LIVE, 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PartiallyReceived => 'Partially Received',
            self::FullyReceived => 'Fully Received',
            default => str($this->name)->headline()->value(),
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isOpen(): bool
    {
        return in_array($this, self::OPEN, true);
    }

    /** Header and lines may only change while the order is still a draft. */
    public function allowsEditing(): bool
    {
        return $this === self::Draft;
    }

    public function allowsDeletion(): bool
    {
        return $this === self::Draft;
    }

    public function allowsPosting(): bool
    {
        return $this === self::Draft;
    }

    /** Goods receipts may only be raised against an order still expecting stock. */
    public function allowsReceiving(): bool
    {
        return $this->isOpen();
    }

    public function allowsCancellation(): bool
    {
        return $this !== self::Cancelled;
    }

    /** Posted (nothing received yet) and cancelled orders can go back to draft. */
    public function allowsRevert(): bool
    {
        return in_array($this, [self::Posted, self::Cancelled], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Posted, self::Cancelled],
            self::Posted => [self::Draft, self::PartiallyReceived, self::FullyReceived, self::Cancelled],
            self::PartiallyReceived => [self::Posted, self::FullyReceived, self::Cancelled],
            self::FullyReceived => [self::Posted, self::PartiallyReceived, self::Cancelled],
            self::Cancelled => [self::Draft],
        };
    }
}
