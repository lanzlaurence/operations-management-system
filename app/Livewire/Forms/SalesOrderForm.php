<?php

namespace App\Livewire\Forms;

use App\Data\DocumentChargeData;
use App\Data\SalesOrderData;
use App\Enums\DiscountType;
use App\Enums\VatType;
use App\Models\Charge;
use App\Models\Material;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use App\Services\Support\DocumentTotals;
use App\Services\Support\DocumentTotalsCalculator;
use App\Services\Support\LineCalculator;
use App\Services\Support\LineTotals;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The sales order form: header, line items and charges.
 *
 * The pricing is not duplicated here. Every figure on screen comes from
 * LineCalculator and DocumentTotalsCalculator - the same classes the service
 * uses when it writes - so the live totals a buyer sees while typing are the
 * totals that get saved, to the cent.
 *
 * Rows are held as plain arrays because Livewire has to serialise them between
 * requests; they are converted to the data objects only at save time.
 */
class SalesOrderForm extends Form
{
    public ?SalesOrder $order = null;

    // Header
    public string $customer_id = '';

    public string $order_date = '';

    public string $delivery_date = '';

    public string $reference_no = '';

    public string $discount_type = '';

    public string $discount_amount = '0';

    public string $remarks = '';

    /**
     * Line items. Keys mirror the payload the data object expects.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    /**
     * Charge rows, each holding only the master charge id.
     *
     * @var array<int, array{charge_id: string}>
     */
    public array $charges = [];

    public function mountForm(): void
    {
        $this->order_date = today()->toDateString();
        $this->items = [$this->blankItem()];
    }

    public function setOrder(SalesOrder $order): void
    {
        $this->order = $order;

        $this->customer_id = (string) $order->customer_id;
        $this->order_date = $order->order_date?->toDateString() ?? today()->toDateString();
        $this->delivery_date = $order->delivery_date?->toDateString() ?? '';
        $this->reference_no = (string) $order->reference_no;
        $this->discount_type = $order->discount_type?->value ?? '';
        $this->discount_amount = (string) Money::round($order->discount_amount);
        $this->remarks = (string) $order->remarks;

        $this->items = $order->items
            ->map(fn ($item): array => [
                'material_id' => (string) $item->material_id,
                'qty_ordered' => (string) Money::quantity($item->qty_ordered),
                'unit_price' => (string) Money::round($item->unit_price),
                'discount_type' => $item->discount_type?->value ?? '',
                'discount_amount' => (string) Money::round($item->discount_amount),
                'is_vatable' => (bool) $item->is_vatable,
                'vat_type' => $item->vat_type?->value ?? VatType::Exclusive->value,
                'vat_rate' => (string) Money::round($item->vat_rate ?: LineCalculator::DEFAULT_VAT_RATE),
                'remarks' => (string) $item->remarks,
                // Shipped quantity is not editable; it guards the quantity field.
                'qty_shipped' => (float) $item->qty_shipped,
            ])
            ->all();

        $this->charges = $order->charges
            ->map(fn ($charge): array => ['charge_id' => (string) $charge->charge_id])
            ->all();

        if ($this->items === []) {
            $this->items = [$this->blankItem()];
        }
    }

    // ── Row management ───────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function blankItem(): array
    {
        return [
            'material_id' => '',
            'qty_ordered' => '1',
            'unit_price' => '0',
            'discount_type' => '',
            'discount_amount' => '0',
            'is_vatable' => false,
            'vat_type' => VatType::Exclusive->value,
            'vat_rate' => (string) LineCalculator::DEFAULT_VAT_RATE,
            'remarks' => '',
            'qty_shipped' => 0.0,
        ];
    }

    public function addItem(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->items = [$this->blankItem()];
        }
    }

    public function addCharge(): void
    {
        $this->charges[] = ['charge_id' => ''];
    }

    public function removeCharge(int $index): void
    {
        unset($this->charges[$index]);

        $this->charges = array_values($this->charges);
    }

    /**
     * Fill a row's cost from the material's list cost when the material is
     * chosen and nothing has been typed yet.
     */
    public function applyMaterialDefaults(int $index, ?Material $material): void
    {
        if ($material === null) {
            return;
        }

        $row = $this->items[$index] ?? null;

        if ($row === null) {
            return;
        }

        if ((float) $row['unit_price'] === 0.0) {
            $this->items[$index]['unit_price'] = (string) Money::round($material->unit_price);
        }
    }

    // ── Live figures ─────────────────────────────────────────────────────────

    /**
     * The computed money columns for one row, or null when it has no material.
     */
    public function lineTotals(int $index, LineCalculator $calculator): ?LineTotals
    {
        $row = $this->items[$index] ?? null;

        if ($row === null || $row['material_id'] === '') {
            return null;
        }

        $isVatable = (bool) $row['is_vatable'];

        return $calculator->calculate(
            quantity: (float) $row['qty_ordered'],
            unitAmount: (float) $row['unit_price'],
            discountType: DiscountType::parse($row['discount_type'] ?: null),
            discountAmount: (float) $row['discount_amount'],
            isVatable: $isVatable,
            vatType: $isVatable ? VatType::parse($row['vat_type'], VatType::Exclusive) : null,
            vatRate: $isVatable ? (float) $row['vat_rate'] : 0.0,
        );
    }

    /**
     * Document totals as they stand, including the resolved charge amounts.
     */
    public function totals(LineCalculator $calculator, DocumentTotalsCalculator $totals): DocumentTotals
    {
        $lines = [];

        foreach (array_keys($this->items) as $index) {
            $line = $this->lineTotals($index, $calculator);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $totals->compute(
            lines: $lines,
            headerDiscountType: DiscountType::parse($this->discount_type ?: null),
            headerDiscountAmount: (float) $this->discount_amount,
            charges: DocumentChargeData::collectionFromRows($this->chargeRows()),
        );
    }

    /**
     * Charge rows with an actual selection, in payload shape.
     *
     * @return array<int, array{charge_id: string}>
     */
    public function chargeRows(): array
    {
        return array_values(array_filter(
            $this->charges,
            fn (array $row): bool => ($row['charge_id'] ?? '') !== '',
        ));
    }

    /**
     * The charges chosen, keyed by row, for displaying their computed amounts.
     *
     * @return array<int, Charge>
     */
    public function selectedCharges(): array
    {
        $ids = collect($this->charges)->pluck('charge_id')->filter();

        if ($ids->isEmpty()) {
            return [];
        }

        $charges = Charge::query()->whereKey($ids->unique())->get()->keyBy('id');

        return collect($this->charges)
            ->map(fn (array $row): ?Charge => $charges->get((int) ($row['charge_id'] ?: 0)))
            ->all();
    }

    // ── Validation ───────────────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('status', 'active'),
            ],
            'order_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['nullable', DiscountType::rule()],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'required_with:discount_type'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.material_id' => ['required', 'integer', 'exists:materials,id', 'distinct'],
            'items.*.qty_ordered' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999',
                // An edit cannot take a line below what has already arrived.
                function (string $attribute, mixed $value, callable $fail): void {
                    $index = (int) explode('.', $attribute)[1];
                    $shipped = (float) ($this->items[$index]['qty_shipped'] ?? 0);

                    if ($shipped > 0 && (float) $value < $shipped) {
                        $fail(sprintf('Cannot go below the %s already shipped on this line.', $shipped + 0));
                    }
                },
            ],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'items.*.discount_type' => ['nullable', DiscountType::rule()],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_vatable' => ['boolean'],
            'items.*.vat_type' => ['nullable', VatType::rule()],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],

            'charges' => ['array', 'max:20'],
            'charges.*.charge_id' => ['nullable', 'integer', 'exists:charges,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Select the customer this order goes to.',
            'customer_id.exists' => 'Select an active customer.',
            'items.required' => 'Add at least one item.',
            'items.*.material_id.required' => 'Select a material on every row.',
            'items.*.material_id.distinct' => 'This material is already on the order; combine the quantities.',
            'items.*.qty_ordered.gt' => 'Quantity must be greater than zero.',
            'delivery_date.after_or_equal' => 'The delivery date cannot be before the order date.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'customer_id' => 'customer',
            'order_date' => 'order date',
            'delivery_date' => 'delivery date',
            'reference_no' => 'reference number',
            'discount_amount' => 'discount amount',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        foreach (['reference_no', 'remarks'] as $field) {
            $attributes[$field] = trim((string) ($attributes[$field] ?? ''));
        }

        // Empty selects and dates are nulls, not empty strings.
        foreach (['delivery_date', 'discount_type'] as $field) {
            if (($attributes[$field] ?? '') === '') {
                $attributes[$field] = null;
            }
        }

        if ($attributes['discount_type'] === null) {
            $attributes['discount_amount'] = 0;
        }

        $attributes['items'] = collect($attributes['items'] ?? [])
            ->map(function (array $row): array {
                $isVatable = (bool) ($row['is_vatable'] ?? false);

                $row['discount_type'] = ($row['discount_type'] ?? '') === '' ? null : $row['discount_type'];
                $row['discount_amount'] = $row['discount_type'] === null ? 0 : $row['discount_amount'];
                $row['vat_type'] = $isVatable ? ($row['vat_type'] ?: VatType::Exclusive->value) : null;
                $row['vat_rate'] = $isVatable ? $row['vat_rate'] : 0;
                $row['remarks'] = trim((string) ($row['remarks'] ?? ''));

                return $row;
            })
            ->all();

        $attributes['charges'] = $this->chargeRows();

        return $attributes;
    }

    // ── Persistence ──────────────────────────────────────────────────────────

    /**
     * Create or update through the service, which owns every rule about what
     * may change and recomputes the stored totals itself.
     */
    public function save(SalesOrderService $orders): SalesOrder
    {
        $data = $this->validate();

        $payload = SalesOrderData::fromArray([
            'customer_id' => $data['customer_id'],
            'order_date' => $data['order_date'],
            'delivery_date' => $data['delivery_date'],
            'reference_no' => $data['reference_no'],
            'discount_type' => $data['discount_type'],
            'discount_amount' => $data['discount_amount'],
            'remarks' => $data['remarks'],
            'items' => $data['items'],
            'charges' => $data['charges'],
        ]);

        return $this->order === null
            ? $orders->create($payload)
            : $orders->update($this->order, $payload);
    }
}
