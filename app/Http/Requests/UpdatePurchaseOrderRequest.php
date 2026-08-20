<?php

namespace App\Http\Requests;

use App\Data\PurchaseOrderData;
use App\Enums\RecordStatus;
use App\Http\Requests\Concerns\ValidatesOrderPayload;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an edit to an existing purchase order.
 *
 * Whether the order may be edited at all (draft only) is decided by
 * PurchaseOrderService, so the user is redirected with an explanation instead
 * of seeing a validation error on a field they cannot see.
 */
class UpdatePurchaseOrderRequest extends FormRequest
{
    use ValidatesOrderPayload;

    public function authorize(): bool
    {
        $order = $this->route('purchase_order');

        return $order instanceof PurchaseOrder
            && ($this->user()?->can('update', $order) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseOrderPayload();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => [
                'required',
                'integer',
                Rule::exists('vendors', 'id')->where('status', RecordStatus::Active->value),
            ],
            ...$this->headerRules(),
            ...$this->lineRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vendor_id.exists' => 'Select an active vendor.',
            ...$this->orderMessages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vendor_id' => 'vendor',
            ...$this->orderAttributes(),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->orderAfterHooks();
    }

    public function toData(): PurchaseOrderData
    {
        return PurchaseOrderData::fromRequest($this);
    }

    protected function unitKey(): string
    {
        return 'unit_cost';
    }
}
