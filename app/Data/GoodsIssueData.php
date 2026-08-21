<?php

namespace App\Data;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validated payload of a goods issue create/update request.
 */
final readonly class GoodsIssueData
{
    /**
     * @param  array<int, MovementLineData>  $lines
     */
    public function __construct(
        public ?int $salesOrderId,
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
            salesOrderId: isset($payload['sales_order_id']) ? (int) $payload['sales_order_id'] : null,
            locationId: (int) $payload['location_id'],
            documentDate: (string) $payload['gi_date'],
            transactionDate: (string) $payload['transaction_date'],
            remarks: self::nullableString($payload['remarks'] ?? null),
            lines: MovementLineData::collectionFromRows(
                $payload['items'] ?? [],
                'sales_order_item_id',
                'qty_to_ship',
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toHeaderColumns(): array
    {
        return [
            'location_id' => $this->locationId,
            'gi_date' => $this->documentDate,
            'transaction_date' => $this->transactionDate,
            'remarks' => $this->remarks,
        ];
    }

    /**
     * Quantity keyed by sales order item id.
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
