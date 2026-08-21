<?php

namespace App\Livewire\Forms;

use App\Data\GoodsReceiptData;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use App\Support\Money;
use Livewire\Form;

/**
 * The goods receipt form.
 *
 * A receipt is built from its purchase order: one row per order line, each
 * carrying the outstanding quantity as its limit. Rows left at zero are simply
 * not part of the receipt, which is what lets a delivery cover some lines and
 * not others.
 */
class GoodsReceiptForm extends Form
{
    public ?GoodsReceipt $receipt = null;

    public ?PurchaseOrder $order = null;

    public string $location_id = '';

    public string $gr_date = '';

    public string $transaction_date = '';

    public string $remarks = '';

    /**
     * One row per order line: the line id, what to receive now, and the
     * serial/batch references if the material is tracked.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    /**
     * Start a new receipt for an order, pre-filling each line with everything
     * still outstanding - the common case is receiving the whole delivery.
     */
    public function mountFor(PurchaseOrder $order, PurchaseOrderService $orders): void
    {
        $this->order = $order;
        $this->gr_date = today()->toDateString();
        $this->transaction_date = today()->toDateString();

        $outstanding = $orders->outstandingQuantities($order);

        $this->items = $order->items
            ->map(fn ($item): array => [
                'purchase_order_item_id' => (string) $item->id,
                'material' => $item->material?->name,
                'material_code' => $item->material?->code,
                'uom' => $item->material?->uom?->acronym,
                'tracks_serial' => (bool) $item->material?->track_serial_number,
                'tracks_batch' => (bool) $item->material?->track_batch_number,
                'qty_ordered' => Money::quantity($item->qty_ordered),
                'qty_received' => Money::quantity($item->qty_received),
                'outstanding' => Money::quantity($outstanding[$item->id] ?? 0),
                'qty_to_receive' => (string) Money::quantity($outstanding[$item->id] ?? 0),
                'unit_cost' => Money::round($item->unit_cost_after_discount),
                'serial_number' => '',
                'batch_number' => '',
                'remarks' => '',
            ])
            ->all();
    }

    /**
     * Load an existing pending receipt for editing. Its own quantities are
     * excluded from the outstanding figures, so a line already on this receipt
     * is not counted against itself.
     */
    public function setReceipt(GoodsReceipt $receipt, PurchaseOrderService $orders): void
    {
        $this->receipt = $receipt;
        $this->order = $receipt->purchaseOrder;

        $this->location_id = (string) $receipt->location_id;
        $this->gr_date = $receipt->gr_date?->toDateString() ?? today()->toDateString();
        $this->transaction_date = $receipt->transaction_date?->toDateString() ?? today()->toDateString();
        $this->remarks = (string) $receipt->remarks;

        $outstanding = $orders->outstandingQuantities($this->order, ignoreReceiptId: $receipt->id);
        $existing = $receipt->items->keyBy('purchase_order_item_id');

        $this->items = $this->order->items
            ->map(function ($item) use ($outstanding, $existing): array {
                $row = $existing->get($item->id);

                return [
                    'purchase_order_item_id' => (string) $item->id,
                    'material' => $item->material?->name,
                    'material_code' => $item->material?->code,
                    'uom' => $item->material?->uom?->acronym,
                    'tracks_serial' => (bool) $item->material?->track_serial_number,
                    'tracks_batch' => (bool) $item->material?->track_batch_number,
                    'qty_ordered' => Money::quantity($item->qty_ordered),
                    'qty_received' => Money::quantity($item->qty_received),
                    'outstanding' => Money::quantity($outstanding[$item->id] ?? 0),
                    'qty_to_receive' => (string) Money::quantity($row?->qty_to_receive ?? 0),
                    'unit_cost' => Money::round($item->unit_cost_after_discount),
                    'serial_number' => (string) ($row?->serial_number ?? ''),
                    'batch_number' => (string) ($row?->batch_number ?? ''),
                    'remarks' => (string) ($row?->remarks ?? ''),
                ];
            })
            ->all();
    }

    /**
     * Fill every row with everything outstanding.
     */
    public function receiveAll(): void
    {
        foreach ($this->items as $index => $row) {
            $this->items[$index]['qty_to_receive'] = (string) $row['outstanding'];
        }
    }

    public function receiveNone(): void
    {
        foreach (array_keys($this->items) as $index) {
            $this->items[$index]['qty_to_receive'] = '0';
        }
    }

    /**
     * Rows with something to receive, in payload shape.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeRows(): array
    {
        return array_values(array_filter(
            $this->items,
            fn (array $row): bool => (float) $row['qty_to_receive'] > 0,
        ));
    }

    /**
     * What this receipt adds to inventory, at the order's agreed unit costs.
     *
     * @return array<string, float>
     */
    public function summary(): array
    {
        $quantity = 0.0;
        $value = 0.0;

        foreach ($this->activeRows() as $row) {
            $quantity += (float) $row['qty_to_receive'];
            $value += (float) $row['qty_to_receive'] * (float) $row['unit_cost'];
        }

        return [
            'lines' => count($this->activeRows()),
            'quantity' => Money::quantity($quantity),
            'value' => Money::round($value),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'gr_date' => ['required', 'date'],
            'transaction_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            'items' => [
                'required',
                'array',
                'min:1',
                // A receipt with every line at zero moves nothing.
                function (string $attribute, mixed $value, callable $fail): void {
                    if ($this->activeRows() === []) {
                        $fail('Enter a quantity on at least one line.');
                    }
                },
            ],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.qty_to_receive' => [
                'required',
                'numeric',
                'min:0',
                // Never more than the order still has outstanding on that line.
                function (string $attribute, mixed $value, callable $fail): void {
                    $index = (int) explode('.', $attribute)[1];
                    $outstanding = (float) ($this->items[$index]['outstanding'] ?? 0);

                    if ((float) $value > $outstanding) {
                        $fail(sprintf('Only %s outstanding on this line.', $outstanding + 0));
                    }
                },
            ],
            'items.*.serial_number' => ['nullable', 'string', 'max:255'],
            'items.*.batch_number' => ['nullable', 'string', 'max:255'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_id.required' => 'Select the location the stock is arriving at.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'location_id' => 'location',
            'gr_date' => 'receipt date',
            'transaction_date' => 'transaction date',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['remarks'] = trim((string) ($attributes['remarks'] ?? ''));

        return $attributes;
    }

    /**
     * Create or update through the service, which snapshots the order lines and
     * re-checks the outstanding quantities itself.
     */
    public function save(GoodsReceiptService $receipts): GoodsReceipt
    {
        $data = $this->validate();

        $payload = GoodsReceiptData::fromArray([
            'purchase_order_id' => $this->order->id,
            'location_id' => $data['location_id'],
            'gr_date' => $data['gr_date'],
            'transaction_date' => $data['transaction_date'],
            'remarks' => $data['remarks'],
            'items' => $this->activeRows(),
        ]);

        return $this->receipt === null
            ? $receipts->create($this->order, $payload)
            : $receipts->update($this->receipt, $payload);
    }
}
