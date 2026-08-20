<?php

namespace App\Services\Support;

/**
 * The computed money columns of a document header, plus the resolved amount of
 * each charge line (indexed the same way the charges were passed in).
 */
final readonly class DocumentTotals
{
    /**
     * @param  array<int, float>  $chargeAmounts
     */
    public function __construct(
        public float $totalBeforeDiscount,
        public float $totalItemDiscount,
        public float $totalNetPrice,
        public float $totalVat,
        public float $totalGross,
        public float $headerDiscountTotal,
        public float $totalCharges,
        public float $grandTotal,
        public array $chargeAmounts = [],
    ) {}

    /**
     * Header columns, keyed as stored on `purchase_orders` / `sales_orders`.
     *
     * @return array<string, float>
     */
    public function toColumns(): array
    {
        return [
            'total_before_discount' => $this->totalBeforeDiscount,
            'total_item_discount' => $this->totalItemDiscount,
            'total_net_price' => $this->totalNetPrice,
            'total_vat' => $this->totalVat,
            'total_gross' => $this->totalGross,
            'header_discount_total' => $this->headerDiscountTotal,
            'total_charges' => $this->totalCharges,
            'grand_total' => $this->grandTotal,
        ];
    }

    public function amountForCharge(int $index): float
    {
        return $this->chargeAmounts[$index] ?? 0.0;
    }
}
