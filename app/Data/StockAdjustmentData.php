<?php

namespace App\Data;

use App\Enums\StockAdjustmentAction;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validated payload of the manual inventory adjustment screen.
 */
final readonly class StockAdjustmentData
{
    public function __construct(
        public StockAdjustmentAction $action,
        public string $transactionDate,
        public int $locationId,
        public ?int $materialId,
        public ?int $inventoryId,
        public float $quantity,
        public ?int $transferLocationId,
        public ?string $remarks,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return self::fromArray($validated);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            action: StockAdjustmentAction::from((string) $payload['action']),
            transactionDate: (string) $payload['transaction_date'],
            locationId: (int) $payload['location_id'],
            materialId: isset($payload['material_id']) ? (int) $payload['material_id'] : null,
            inventoryId: isset($payload['inventory_id']) ? (int) $payload['inventory_id'] : null,
            quantity: Money::quantity($payload['quantity'] ?? 0),
            transferLocationId: isset($payload['transfer_location_id']) ? (int) $payload['transfer_location_id'] : null,
            remarks: self::nullableString($payload['remarks'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
