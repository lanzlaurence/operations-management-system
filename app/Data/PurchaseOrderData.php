<?php

namespace App\Data;

use App\Enums\DiscountType;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validated payload of a purchase order create/update request.
 *
 * Controllers hand this to PurchaseOrderService, so the service never touches
 * the HTTP layer and the same call is available to seeders, jobs and tests.
 */
final readonly class PurchaseOrderData
{
    /**
     * @param  array<int, OrderLineData>  $lines
     * @param  array<int, DocumentChargeData>  $charges
     */
    public function __construct(
        public int $vendorId,
        public string $orderDate,
        public ?string $deliveryDate,
        public ?string $referenceNo,
        public ?DiscountType $discountType,
        public float $discountAmount,
        public ?string $remarks,
        public array $lines,
        public array $charges = [],
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
        $discountType = DiscountType::parse($payload['discount_type'] ?? null);

        return new self(
            vendorId: (int) $payload['vendor_id'],
            orderDate: (string) $payload['order_date'],
            deliveryDate: self::nullableString($payload['delivery_date'] ?? null),
            referenceNo: self::nullableString($payload['reference_no'] ?? null),
            discountType: $discountType,
            discountAmount: $discountType === null ? 0.0 : Money::round($payload['discount_amount'] ?? 0),
            remarks: self::nullableString($payload['remarks'] ?? null),
            lines: OrderLineData::collectionFromRows($payload['items'] ?? [], 'unit_cost'),
            charges: DocumentChargeData::collectionFromRows($payload['charges'] ?? []),
        );
    }

    /**
     * Header columns shared by create and update.
     *
     * @return array<string, mixed>
     */
    public function toHeaderColumns(): array
    {
        return [
            'vendor_id' => $this->vendorId,
            'order_date' => $this->orderDate,
            'delivery_date' => $this->deliveryDate,
            'reference_no' => $this->referenceNo,
            'discount_type' => $this->discountType?->value,
            'discount_amount' => $this->discountAmount,
            'remarks' => $this->remarks,
        ];
    }

    /**
     * @return array<int, int>
     */
    public function materialIds(): array
    {
        return array_values(array_unique(array_map(
            fn (OrderLineData $line): int => $line->materialId,
            $this->lines,
        )));
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
