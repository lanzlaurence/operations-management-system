<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Lifecycle of a sales order. Mirrors PurchaseOrderStatus with shipping
 * instead of receiving; the shipping statuses are derived from the completed
 * goods issues by SalesOrderService::syncIssueState().
 */
enum SalesOrderStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Posted = 'posted';
    case PartiallyShipped = 'partially_shipped';
    case FullyShipped = 'fully_shipped';
    case Cancelled = 'cancelled';

    /** Statuses with shipping still outstanding. */
    public const OPEN = [self::Posted, self::PartiallyShipped];

    /** Statuses that count towards sales figures (draft excluded). */
    public const LIVE = [self::Posted, self::PartiallyShipped, self::FullyShipped];

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
            self::PartiallyShipped => 'Partially Shipped',
            self::FullyShipped => 'Fully Shipped',
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

    /** Goods issues may only be raised against an order still owing stock. */
    public function allowsIssuing(): bool
    {
        return $this->isOpen();
    }

    public function allowsCancellation(): bool
    {
        return $this !== self::Cancelled;
    }

    /** Posted (nothing shipped yet) and cancelled orders can go back to draft. */
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
            self::Posted => [self::Draft, self::PartiallyShipped, self::FullyShipped, self::Cancelled],
            self::PartiallyShipped => [self::Posted, self::FullyShipped, self::Cancelled],
            self::FullyShipped => [self::Posted, self::PartiallyShipped, self::Cancelled],
            self::Cancelled => [self::Draft],
        };
    }
}
