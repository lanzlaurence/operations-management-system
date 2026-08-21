<?php

namespace App\Data;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validated payload of a goods receipt create/update request.
 */
final readonly class GoodsReceiptData
{
    /**
     * @param  array<int, MovementLineData>  $lines
     */
    public function __construct(
        public ?int $purchaseOrderId,
        public int $locationId,
        public string $documentDate,
        public string $transactionDate,
        public ?string $remarks,
        public array $lines,
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
            purchaseOrderId: isset($payload['purchase_order_id']) ? (int) $payload['purchase_order_id'] : null,
            locationId: (int) $payload['location_id'],
            documentDate: (string) $payload['gr_date'],
            transactionDate: (string) $payload['transaction_date'],
            remarks: self::nullableString($payload['remarks'] ?? null),
            lines: MovementLineData::collectionFromRows(
                $payload['items'] ?? [],
                'purchase_order_item_id',
                'qty_to_receive',
            ),
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
            'location_id' => $this->locationId,
            'gr_date' => $this->documentDate,
            'transaction_date' => $this->transactionDate,
            'remarks' => $this->remarks,
        ];
    }

    /**
     * Quantity keyed by purchase order item id.
     *
     * @return array<int, float>
     */
    public function quantitiesBySourceItem(): array
    {
        $quantities = [];

        foreach ($this->lines as $line) {
            $quantities[$line->sourceItemId] = ($quantities[$line->sourceItemId] ?? 0) + $line->quantity;
        }

        return $quantities;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
