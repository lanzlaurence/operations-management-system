<?php

namespace App\Data;

use App\Enums\DiscountType;
use App\Enums\VatType;
use App\Services\Support\LineCalculator;
use App\Support\Money;

/**
 * One validated order line, used by both purchase and sales orders.
 *
 * The only difference between the two is the name of the unit column in the
 * payload (`unit_cost` vs `unit_price`), which is why `fromRow()` takes the
 * key to read.
 */
final readonly class OrderLineData
{
    public function __construct(
        public int $materialId,
        public float $quantity,
        public float $unitAmount,
        public ?DiscountType $discountType,
        public float $discountAmount,
        public bool $isVatable,
        public ?VatType $vatType,
        public float $vatRate,
        public ?string $remarks,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     * @param  string  $unitKey  `unit_cost` for purchasing, `unit_price` for sales
     */
    public static function fromRow(array $row, string $unitKey): self
    {
        $isVatable = filter_var($row['is_vatable'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new self(
            materialId: (int) $row['material_id'],
            quantity: Money::quantity($row['qty_ordered'] ?? 0),
            unitAmount: Money::round($row[$unitKey] ?? 0),
            discountType: DiscountType::parse($row['discount_type'] ?? null),
            discountAmount: Money::round($row['discount_amount'] ?? 0),
            isVatable: $isVatable,
            vatType: $isVatable ? VatType::parse($row['vat_type'] ?? null, VatType::Exclusive) : null,
            vatRate: $isVatable ? Money::round($row['vat_rate'] ?? LineCalculator::DEFAULT_VAT_RATE) : 0.0,
            remarks: self::nullableString($row['remarks'] ?? null),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, self>
     */
    public static function collectionFromRows(array $rows, string $unitKey): array
    {
        return array_values(array_map(
            fn (array $row): self => self::fromRow($row, $unitKey),
            $rows,
        ));
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
