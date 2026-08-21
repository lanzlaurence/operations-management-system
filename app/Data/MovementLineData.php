<?php

namespace App\Data;

use App\Support\Money;

/**
 * One line of a stock document (goods receipt or goods issue).
 *
 * `sourceItemId` points at the purchase order item being received or the sales
 * order item being shipped, and `quantity` is the amount this document moves.
 */
final readonly class MovementLineData
{
    public function __construct(
        public int $sourceItemId,
        public float $quantity,
        public ?string $serialNumber,
        public ?string $batchNumber,
        public ?string $remarks,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     * @param  string  $itemKey  `purchase_order_item_id` or `sales_order_item_id`
     * @param  string  $qtyKey  `qty_to_receive` or `qty_to_ship`
     */
    public static function fromRow(array $row, string $itemKey, string $qtyKey): self
    {
        return new self(
            sourceItemId: (int) $row[$itemKey],
            quantity: Money::quantity($row[$qtyKey] ?? 0),
            serialNumber: self::nullableString($row['serial_number'] ?? null),
            batchNumber: self::nullableString($row['batch_number'] ?? null),
            remarks: self::nullableString($row['remarks'] ?? null),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, self>
     */
    public static function collectionFromRows(array $rows, string $itemKey, string $qtyKey): array
    {
        return array_values(array_map(
            fn (array $row): self => self::fromRow($row, $itemKey, $qtyKey),
            $rows,
        ));
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
