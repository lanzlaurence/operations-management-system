<?php

namespace App\Http\Requests;

use App\Models\GoodsReceipt;
use Illuminate\Validation\Validator;

/**
 * Validates an edit to a pending goods receipt.
 *
 * Reuses the store rules, but resolves the purchase order from the receipt
 * being edited and excludes that receipt from the outstanding calculation so
 * its own quantities are not counted against it.
 */
class UpdateGoodsReceiptRequest extends StoreGoodsReceiptRequest
{
    public function authorize(): bool
    {
        $receipt = $this->receipt();

        return $receipt !== null && ($this->user()?->can('update', $receipt) ?? false);
    }

    /**
     * The purchase order is taken from the receipt, never from the payload.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'purchase_order_id' => $this->receipt()?->purchase_order_id,
        ]);
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

    protected function ignoredReceiptId(): ?int
    {
        return $this->receipt()?->id;
    }

    private function receipt(): ?GoodsReceipt
    {
        $receipt = $this->route('goods_receipt');

        return $receipt instanceof GoodsReceipt ? $receipt : null;
    }
}
