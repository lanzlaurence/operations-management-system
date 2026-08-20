<?php

namespace App\Http\Requests;

use App\Data\GoodsIssueData;
use App\Http\Requests\Concerns\ValidatesStockDocumentPayload;
use App\Models\GoodsIssue;
use App\Models\SalesOrder;
use App\Services\InventoryService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a new goods issue.
 *
 * Two limits apply per line: what the sales order still owes the customer,
 * and what the chosen location can still promise (on hand minus the
 * quantities other pending issues have already reserved).
 */
class StoreGoodsIssueRequest extends FormRequest
{
    use ValidatesStockDocumentPayload;

    public function authorize(): bool
    {
        return $this->user()?->can('create', GoodsIssue::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseStockPayload();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            ...$this->stockDocumentRules('sales_order_items'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->stockDocumentMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->stockDocumentAttributes();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateAgainstOrder($validator),
        ];
    }

    /**
     * Check each line against the order outstanding quantity and the stock
     * available at the selected location.
     */
    protected function validateAgainstOrder(Validator $validator): void
    {
        $order = SalesOrder::with('items.material')->find($this->input('sales_order_id'));
        $locationId = (int) $this->input('location_id');

        if ($order === null || $locationId <= 0) {
            return;
        }

        if (! $order->status->allowsIssuing()) {
            $validator->errors()->add('sales_order_id', 'This sales order is not open for shipping.');

            return;
        }

        $outstanding = app(SalesOrderService::class)->outstandingQuantities($order, $this->ignoredIssueId());
        $inventory = app(InventoryService::class);
        $orderItems = $order->items->keyBy('id');

        foreach ((array) $this->input('items', []) as $index => $item) {
            $itemId = (int) ($item[$this->orderItemKey()] ?? 0);
            $quantity = (float) ($item[$this->quantityKey()] ?? 0);

            $orderItem = $orderItems->get($itemId);

            if ($orderItem === null) {
                $validator->errors()->add(
                    "items.{$index}.".$this->orderItemKey(),
                    'This item does not belong to the selected sales order.',
                );

                continue;
            }

            $limit = (float) ($outstanding[$itemId] ?? 0);

            if ($quantity > $limit) {
                $validator->errors()->add(
                    "items.{$index}.".$this->quantityKey(),
                    "Quantity to ship cannot exceed the outstanding {$limit}.",
                );

                continue;
            }

            $available = $inventory->availableQuantity($orderItem->material_id, $locationId, $this->ignoredIssueId());

            if ($quantity > $available) {
                $validator->errors()->add(
                    "items.{$index}.".$this->quantityKey(),
                    sprintf(
                        'Insufficient stock for [%s] %s. Available: %s, Required: %s.',
                        $orderItem->material?->code ?? '',
                        $orderItem->material?->name ?? '',
                        $available + 0,
                        $quantity + 0,
                    ),
                );
            }
        }
    }

    /** The issue being edited, so its own reservations are not double counted. */
    protected function ignoredIssueId(): ?int
    {
        return null;
    }

    public function toData(): GoodsIssueData
    {
        return GoodsIssueData::fromRequest($this);
    }

    protected function quantityKey(): string
    {
        return 'qty_to_ship';
    }

    protected function orderItemKey(): string
    {
        return 'sales_order_item_id';
    }

    protected function documentDateKey(): string
    {
        return 'gi_date';
    }
}
