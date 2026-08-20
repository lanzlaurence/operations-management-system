<?php

namespace App\Http\Requests;

use App\Data\SalesOrderData;
use App\Enums\RecordStatus;
use App\Http\Requests\Concerns\ValidatesOrderPayload;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new sales order.
 */
class StoreSalesOrderRequest extends FormRequest
{
    use ValidatesOrderPayload;

    public function authorize(): bool
    {
        return $this->user()?->can('create', SalesOrder::class) ?? false;
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

    /**
     * The validated payload, ready for SalesOrderService.
     */
    public function toData(): SalesOrderData
    {
        return SalesOrderData::fromRequest($this);
    }

    protected function unitKey(): string
    {
        return 'unit_price';
    }
}
