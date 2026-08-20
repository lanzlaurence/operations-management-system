<?php

namespace App\Http\Requests\Concerns;

use App\Enums\DiscountType;
use App\Enums\RecordStatus;
use App\Enums\VatType;
use App\Models\Charge;
use App\Models\Material;
use Illuminate\Validation\Validator;

/**
 * Shared validation for the purchase and sales order forms.
 *
 * Both documents post the same shape - a header, a list of item rows and a
 * list of charge rows - and differ only in the party (`vendor_id` /
 * `customer_id`) and the unit column (`unit_cost` / `unit_price`). Keeping the
 * rules here means a rule added for purchasing cannot be forgotten for sales.
 */
trait ValidatesOrderPayload
{
    /**
     * The unit amount key this document posts: `unit_cost` or `unit_price`.
     */
    abstract protected function unitKey(): string;

    /**
     * Turn empty strings into nulls and normalise the item rows before the
     * rules run, so that a cleared discount field is `null` rather than `''`.
     */
    protected function normaliseOrderPayload(): void
    {
        $payload = [];

        foreach (['reference_no', 'remarks', 'delivery_date', 'discount_type'] as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $payload[$key] = null;
            }
        }

        if ($this->input('discount_type') === null || $this->input('discount_type') === '') {
            $payload['discount_type'] = null;
            $payload['discount_amount'] = 0;
        }

        if (is_array($items = $this->input('items'))) {
            $payload['items'] = array_map(fn (mixed $item): mixed => $this->normaliseItem($item), $items);
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @param  mixed  $item
     * @return mixed
     */
    private function normaliseItem(mixed $item): mixed
    {
        if (! is_array($item)) {
            return $item;
        }

        $isVatable = filter_var($item['is_vatable'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $item['is_vatable'] = $isVatable;

        if (($item['discount_type'] ?? '') === '') {
            $item['discount_type'] = null;
            $item['discount_amount'] = 0;
        }

        if (! $isVatable) {
            $item['vat_type'] = null;
            $item['vat_rate'] = 0;
        } elseif (($item['vat_type'] ?? '') === '') {
            $item['vat_type'] = VatType::Exclusive->value;
        }

        foreach (['remarks'] as $key) {
            if (($item[$key] ?? null) === '') {
                $item[$key] = null;
            }
        }

        return $item;
    }

    /**
     * Header rules that are identical for both documents.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function headerRules(): array
    {
        return [
            'order_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['nullable', DiscountType::rule()],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'required_with:discount_type'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Item and charge rules, using the unit key of the concrete document.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function lineRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.qty_ordered' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.' . $this->unitKey() => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'items.*.discount_type' => ['nullable', DiscountType::rule()],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'required_with:items.*.discount_type'],
            'items.*.is_vatable' => ['boolean'],
            'items.*.vat_type' => ['nullable', VatType::rule()],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],

            'charges' => ['nullable', 'array', 'max:20'],
            'charges.*.charge_id' => ['required', 'integer', 'exists:charges,id', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function orderMessages(): array
    {
        return [
            'items.required' => 'Add at least one item to the order.',
            'items.*.material_id.required' => 'Select a material for every item row.',
            'items.*.qty_ordered.gt' => 'Quantity must be greater than zero.',
            'items.*.' . $this->unitKey() . '.required' => 'Enter a unit amount for every item row.',
            'charges.*.charge_id.distinct' => 'The same charge cannot be added twice.',
            'discount_amount.required_with' => 'Enter the discount amount.',
            'items.*.discount_amount.required_with' => 'Enter the discount amount for the item.',
            'delivery_date.after_or_equal' => 'Delivery date cannot be earlier than the order date.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function orderAttributes(): array
    {
        return [
            'items.*.material_id' => 'material',
            'items.*.qty_ordered' => 'quantity',
            'items.*.' . $this->unitKey() => str_replace('_', ' ', $this->unitKey()),
            'items.*.discount_amount' => 'item discount',
            'items.*.vat_rate' => 'VAT rate',
            'charges.*.charge_id' => 'charge',
        ];
    }

    /**
     * Cross-field business rules that a single-field rule cannot express.
     *
     * @return array<int, callable>
     */
    protected function orderAfterHooks(): array
    {
        return [
            fn (Validator $validator) => $this->validatePercentageDiscounts($validator),
            fn (Validator $validator) => $this->validateFixedDiscounts($validator),
            fn (Validator $validator) => $this->validateMaterialsAreUniqueAndActive($validator),
            fn (Validator $validator) => $this->validateChargesAreActive($validator),
        ];
    }

    /**
     * A percentage discount above 100% would produce a negative price.
     */
    private function validatePercentageDiscounts(Validator $validator): void
    {
        if ($this->input('discount_type') === DiscountType::Percentage->value
            && (float) $this->input('discount_amount', 0) > 100) {
            $validator->errors()->add('discount_amount', 'A percentage discount cannot exceed 100%.');
        }

        foreach ((array) $this->input('items', []) as $index => $item) {
            if (($item['discount_type'] ?? null) === DiscountType::Percentage->value
                && (float) ($item['discount_amount'] ?? 0) > 100) {
                $validator->errors()->add("items.{$index}.discount_amount", 'A percentage discount cannot exceed 100%.');
            }
        }
    }

    /**
     * A fixed discount larger than the unit amount would produce a negative
     * price; the header discount is checked against the document total.
     */
    private function validateFixedDiscounts(Validator $validator): void
    {
        $unitKey = $this->unitKey();

        foreach ((array) $this->input('items', []) as $index => $item) {
            if (($item['discount_type'] ?? null) !== DiscountType::Fixed->value) {
                continue;
            }

            $unit = (float) ($item[$unitKey] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? 0);

            if ($discount > $unit) {
                $validator->errors()->add(
                    "items.{$index}.discount_amount",
                    'A fixed discount cannot be greater than the unit amount.',
                );
            }
        }
    }

    /**
     * Every material must appear once (the services match order lines to their
     * existing rows by material) and must still be active.
     */
    private function validateMaterialsAreUniqueAndActive(Validator $validator): void
    {
        $items = (array) $this->input('items', []);

        $seen = [];

        foreach ($items as $index => $item) {
            $materialId = $item['material_id'] ?? null;

            if ($materialId === null) {
                continue;
            }

            if (isset($seen[$materialId])) {
                $validator->errors()->add(
                    "items.{$index}.material_id",
                    'This material is already on the order. Combine the quantities into one row.',
                );

                continue;
            }

            $seen[$materialId] = $index;
        }

        if ($seen === []) {
            return;
        }

        $inactive = Material::query()
            ->whereKey(array_keys($seen))
            ->where('status', '!=', RecordStatus::Active->value)
            ->pluck('name', 'id');

        foreach ($inactive as $id => $name) {
            $validator->errors()->add("items.{$seen[$id]}.material_id", "{$name} is inactive and cannot be ordered.");
        }
    }

    private function validateChargesAreActive(Validator $validator): void
    {
        $charges = collect((array) $this->input('charges', []))
            ->pluck('charge_id')
            ->filter()
            ->values();

        if ($charges->isEmpty()) {
            return;
        }

        $inactive = Charge::query()
            ->whereKey($charges)
            ->where('status', '!=', RecordStatus::Active->value)
            ->pluck('name', 'id');

        foreach ($charges as $index => $chargeId) {
            if ($inactive->has((int) $chargeId)) {
                $validator->errors()->add(
                    "charges.{$index}.charge_id",
                    $inactive[(int) $chargeId] . ' is inactive and cannot be applied.',
                );
            }
        }
    }
}
