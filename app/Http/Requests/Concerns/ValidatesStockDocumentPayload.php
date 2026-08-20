<?php

namespace App\Http\Requests\Concerns;

/**
 * Shared validation for the goods receipt and goods issue forms.
 *
 * Both post a header (location, dates, remarks) plus rows that point at an
 * order line and carry the quantity to move. The per-line limits (outstanding
 * quantity, available stock) are re-checked inside the services, where the
 * inventory row is locked; the rules here exist to give the user field-level
 * feedback before that happens.
 */
trait ValidatesStockDocumentPayload
{
    /** `qty_to_receive` or `qty_to_ship`. */
    abstract protected function quantityKey(): string;

    /** `purchase_order_item_id` or `sales_order_item_id`. */
    abstract protected function orderItemKey(): string;

    /** `gr_date` or `gi_date`. */
    abstract protected function documentDateKey(): string;

    protected function normaliseStockPayload(): void
    {
        $payload = [];

        if ($this->input('remarks') === '') {
            $payload['remarks'] = null;
        }

        if (is_array($items = $this->input('items'))) {
            $payload['items'] = array_values(array_filter(
                array_map(fn (mixed $item): mixed => $this->normaliseItem($item), $items),
                fn (mixed $item): bool => ! is_array($item) || (float) ($item[$this->quantityKey()] ?? 0) > 0,
            ));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    private function normaliseItem(mixed $item): mixed
    {
        if (! is_array($item)) {
            return $item;
        }

        foreach (['serial_number', 'batch_number', 'remarks'] as $key) {
            if (($item[$key] ?? null) === '') {
                $item[$key] = null;
            }
        }

        return $item;
    }

    /**
     * @param  string  $orderItemTable  `purchase_order_items` or `sales_order_items`
     * @return array<string, array<int, mixed>>
     */
    protected function stockDocumentRules(string $orderItemTable): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            $this->documentDateKey() => ['required', 'date'],
            'transaction_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.' . $this->orderItemKey() => [
                'required',
                'integer',
                "exists:{$orderItemTable},id",
                'distinct',
            ],
            'items.*.' . $this->quantityKey() => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.serial_number' => ['nullable', 'string', 'max:255'],
            'items.*.batch_number' => ['nullable', 'string', 'max:255'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function stockDocumentMessages(): array
    {
        return [
            'items.required' => 'Enter a quantity on at least one line.',
            'items.*.' . $this->quantityKey() . '.gt' => 'Quantity must be greater than zero.',
            'items.*.' . $this->orderItemKey() . '.distinct' => 'The same order line cannot appear twice.',
            'location_id.required' => 'Select a location.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function stockDocumentAttributes(): array
    {
        return [
            'location_id' => 'location',
            $this->documentDateKey() => 'document date',
            'items.*.' . $this->quantityKey() => 'quantity',
            'items.*.' . $this->orderItemKey() => 'order line',
        ];
    }
}
