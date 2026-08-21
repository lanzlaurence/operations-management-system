<?php

namespace App\Services\Support;

use App\Enums\DiscountType;
use App\Enums\VatType;
use App\Support\Money;

/**
 * The computed money columns of a single document line.
 *
 * Purchase order and sales order lines only differ in the name of the unit
 * column (`unit_cost` vs `unit_price`), so both are built from this one value
 * object and each service maps it onto its own column names.
 */
final readonly class LineTotals
{
    public function __construct(
        public float $quantity,
        public float $unitAmount,
        public ?DiscountType $discountType,
        public float $discountAmount,
        public float $unitAmountAfterDiscount,
        public float $lineDiscountTotal,
        public bool $isVatable,
        public ?VatType $vatType,
        public float $vatRate,
        public float $netPrice,
        public float $vatPrice,
        public float $grossPrice,
    ) {}

    /**
     * Quantity times the undiscounted unit amount: the list value of the line.
     */
    public function baseAmount(): float
    {
        return Money::round($this->quantity * $this->unitAmount);
    }

    /**
     * The shared money columns, keyed exactly as they are stored. The unit
     * column is added by the caller because its name differs per document.
     *
     * @return array<string, float|bool|string|null>
     */
    public function toColumns(): array
    {
        return [
            'discount_type' => $this->discountType?->value,
            'discount_amount' => $this->discountAmount,
            'net_price' => $this->netPrice,
            'is_vatable' => $this->isVatable,
            'vat_type' => $this->vatType?->value,
            'vat_rate' => $this->vatRate,
            'vat_price' => $this->vatPrice,
            'gross_price' => $this->grossPrice,
        ];
    }
}
