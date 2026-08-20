<?php

namespace App\Http\Requests;

use App\Data\GoodsReceiptData;
use App\Http\Requests\Concerns\ValidatesStockDocumentPayload;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a new goods receipt.
 *
 * On top of the shared rules it checks that every line belongs to the given
 * purchase order and that the quantity fits in what is still outstanding
 * (quantities held by other pending receipts included).
 */
class StoreGoodsReceiptRequest extends FormRequest
{
    use ValidatesStockDocumentPayload;

    public function authorize(): bool
    {
        return $this->user()?->can('create', GoodsReceipt::class) ?? false;
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
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            ...$this->stockDocumentRules('purchase_order_items'),
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
     * Each line must belong to the order and stay within its outstanding
     * quantity.
     */
    protected function validateAgainstOrder(Validator $validator): void
    {
        $order = PurchaseOrder::with('items.material')->find($this->input('purchase_order_id'));

        if ($order === null) {
            return;
        }

        if (! $order->status->allowsReceiving()) {
            $validator->errors()->add('purchase_order_id', 'This purchase order is not open for receiving.');

            return;
        }

        $outstanding = app(PurchaseOrderService::class)->outstandingQuantities($order, $this->ignoredReceiptId());
        $orderItems = $order->items->keyBy('id');

        foreach ((array) $this->input('items', []) as $index => $item) {
            $itemId = (int) ($item[$this->orderItemKey()] ?? 0);
            $quantity = (float) ($item[$this->quantityKey()] ?? 0);

            $orderItem = $orderItems->get($itemId);

            if ($orderItem === null) {
                $validator->errors()->add(
                    "items.{$index}.".$this->orderItemKey(),
                    'This item does not belong to the selected purchase order.',
                );

                continue;
            }

            $limit = (float) ($outstanding[$itemId] ?? 0);

            if ($quantity > $limit) {
                $validator->errors()->add(
                    "items.{$index}.".$this->quantityKey(),
                    "Quantity to receive cannot exceed the outstanding {$limit}.",
                );
            }
        }
    }

    /** The receipt being edited, so its own quantities are not counted twice. */
    protected function ignoredReceiptId(): ?int
    {
        return null;
    }

    public function toData(): GoodsReceiptData
    {
        return GoodsReceiptData::fromRequest($this);
    }

    protected function quantityKey(): string
    {
        return 'qty_to_receive';
    }

    protected function orderItemKey(): string
    {
        return 'purchase_order_item_id';
    }

    protected function documentDateKey(): string
    {
        return 'gr_date';
    }
}
