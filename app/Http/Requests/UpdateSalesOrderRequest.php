<?php

namespace App\Http\Requests;

use App\Data\SalesOrderData;
use App\Enums\RecordStatus;
use App\Http\Requests\Concerns\ValidatesOrderPayload;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an edit to an existing sales order.
 *
 * Whether the order may be edited at all (draft only) is decided by
 * SalesOrderService, so the user is redirected with an explanation instead
 * of seeing a validation error on a field they cannot see.
 */
class UpdateSalesOrderRequest extends FormRequest
{
    use ValidatesOrderPayload;

    public function authorize(): bool
    {
        $order = $this->route('sales_order');

        return $order instanceof SalesOrder
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
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('status', RecordStatus::Active->value),
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
            'customer_id.exists' => 'Select an active customer.',
            ...$this->orderMessages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
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

    public function toData(): SalesOrderData
    {
        return SalesOrderData::fromRequest($this);
    }

    protected function unitKey(): string
    {
        return 'unit_price';
    }
}
