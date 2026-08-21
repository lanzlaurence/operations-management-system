<?php

namespace App\Livewire\Forms;

use App\Data\GoodsIssueData;
use App\Models\GoodsIssue;
use App\Models\SalesOrder;
use App\Services\GoodsIssueService;
use App\Services\InventoryService;
use App\Services\SalesOrderService;
use App\Support\Money;
use Livewire\Form;

/**
 * The goods issue form.
 *
 * A issue is built from its sales order: one row per order line, each
 * carrying the outstanding quantity as its limit. Rows left at zero are simply
 * not part of the issue, which is what lets a delivery cover some lines and
 * not others.
 */
class GoodsIssueForm extends Form
{
    public ?GoodsIssue $issue = null;

    public ?SalesOrder $order = null;

    public string $location_id = '';

    public string $gi_date = '';

    public string $transaction_date = '';

    public string $remarks = '';

    /**
     * One row per order line: the line id, what to ship now, and the
     * serial/batch references if the material is tracked.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    /**
     * Start a new issue for an order, pre-filling each line with everything
     * still outstanding - the common case is shipping the whole delivery.
     */
    public function mountFor(SalesOrder $order, SalesOrderService $orders): void
    {
        $this->order = $order;
        $this->gi_date = today()->toDateString();
        $this->transaction_date = today()->toDateString();

        $outstanding = $orders->outstandingQuantities($order);

        $this->items = $order->items
            ->map(fn ($item): array => [
                'sales_order_item_id' => (string) $item->id,
                'material_id' => $item->material_id,
                'material' => $item->material?->name,
                'material_code' => $item->material?->code,
                'uom' => $item->material?->uom?->acronym,
                'tracks_serial' => (bool) $item->material?->track_serial_number,
                'tracks_batch' => (bool) $item->material?->track_batch_number,
                'qty_ordered' => Money::quantity($item->qty_ordered),
                'qty_shipped' => Money::quantity($item->qty_shipped),
                'outstanding' => Money::quantity($outstanding[$item->id] ?? 0),
                'available' => 0.0,
                'can_ship' => 0.0,
                'qty_to_ship' => '0',
                'unit_price' => Money::round($item->unit_price_after_discount),
                'serial_number' => '',
                'batch_number' => '',
                'remarks' => '',
            ])
            ->all();
    }

    /**
     * Load an existing pending issue for editing. Its own quantities are
     * excluded from the outstanding figures, so a line already on this issue
     * is not counted against itself.
     */
    public function setIssue(GoodsIssue $issue, SalesOrderService $orders): void
    {
        $this->issue = $issue;
        $this->order = $issue->salesOrder;

        $this->location_id = (string) $issue->location_id;
        $this->gi_date = $issue->gi_date?->toDateString() ?? today()->toDateString();
        $this->transaction_date = $issue->transaction_date?->toDateString() ?? today()->toDateString();
        $this->remarks = (string) $issue->remarks;

        $outstanding = $orders->outstandingQuantities($this->order, ignoreIssueId: $issue->id);
        $existing = $issue->items->keyBy('sales_order_item_id');

        $this->items = $this->order->items
            ->map(function ($item) use ($outstanding, $existing): array {
                $row = $existing->get($item->id);

                return [
                    'sales_order_item_id' => (string) $item->id,
                    'material_id' => $item->material_id,
                    'material' => $item->material?->name,
                    'material_code' => $item->material?->code,
                    'uom' => $item->material?->uom?->acronym,
                    'tracks_serial' => (bool) $item->material?->track_serial_number,
                    'tracks_batch' => (bool) $item->material?->track_batch_number,
                    'qty_ordered' => Money::quantity($item->qty_ordered),
                    'qty_shipped' => Money::quantity($item->qty_shipped),
                    'outstanding' => Money::quantity($outstanding[$item->id] ?? 0),
                    'available' => 0.0,
                    'can_ship' => 0.0,
                    'qty_to_ship' => (string) Money::quantity($row?->qty_to_ship ?? 0),
                    'unit_price' => Money::round($item->unit_price_after_discount),
                    'serial_number' => (string) ($row?->serial_number ?? ''),
                    'batch_number' => (string) ($row?->batch_number ?? ''),
                    'remarks' => (string) ($row?->remarks ?? ''),
                ];
            })
            ->all();
    }

    /**
     * Fill each row's available quantity from the chosen location.
     *
     * Availability is what is on hand minus everything other pending issues
     * have already reserved there, so two issues cannot promise the same units.
     */
    public function refreshAvailability(InventoryService $inventory): void
    {
        if ($this->location_id === '') {
            foreach (array_keys($this->items) as $index) {
                $this->items[$index]['available'] = 0.0;
                $this->items[$index]['can_ship'] = 0.0;
            }

            return;
        }

        $map = $inventory->availableQuantityMap(
            collect($this->items)->pluck('material_id'),
            $this->issue?->id,
        );

        foreach ($this->items as $index => $row) {
            $available = (float) ($map[$row['material_id']][(int) $this->location_id] ?? 0);

            $this->items[$index]['available'] = Money::quantity($available);
            $this->items[$index]['can_ship'] = Money::quantity(min($available, (float) $row['outstanding']));

            // Never leave a row promising more than the location can cover.
            if ((float) $this->items[$index]['qty_to_ship'] > $this->items[$index]['can_ship']) {
                $this->items[$index]['qty_to_ship'] = (string) $this->items[$index]['can_ship'];
            }
        }
    }

    /**
     * Fill every row with everything it can ship from this location.
     */
    public function shipAll(): void
    {
        foreach ($this->items as $index => $row) {
            $this->items[$index]['qty_to_ship'] = (string) ($row['can_ship'] ?? 0);
        }
    }

    public function shipNone(): void
    {
        foreach (array_keys($this->items) as $index) {
            $this->items[$index]['qty_to_ship'] = '0';
        }
    }

    /**
     * Rows with something to ship, in payload shape.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeRows(): array
    {
        return array_values(array_filter(
            $this->items,
            fn (array $row): bool => (float) $row['qty_to_ship'] > 0,
        ));
    }

    /**
     * What this issue adds to inventory, at the order's agreed unit costs.
     *
     * @return array<string, float>
     */
    public function summary(): array
    {
        $quantity = 0.0;
        $value = 0.0;

        foreach ($this->activeRows() as $row) {
            $quantity += (float) $row['qty_to_ship'];
            $value += (float) $row['qty_to_ship'] * (float) $row['unit_price'];
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
            'gi_date' => ['required', 'date'],
            'transaction_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            'items' => [
                'required',
                'array',
                'min:1',
                // A issue with every line at zero moves nothing.
                function (string $attribute, mixed $value, callable $fail): void {
                    if ($this->activeRows() === []) {
                        $fail('Enter a quantity on at least one line.');
                    }
                },
            ],
            'items.*.sales_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.qty_to_ship' => [
                'required',
                'numeric',
                'min:0',
                // Two limits: what the order still owes, and what the location
                // can actually promise.
                function (string $attribute, mixed $value, callable $fail): void {
                    $index = (int) explode('.', $attribute)[1];
                    $row = $this->items[$index] ?? [];

                    $outstanding = (float) ($row['outstanding'] ?? 0);
                    $available = (float) ($row['available'] ?? 0);

                    if ((float) $value > $outstanding) {
                        $fail(sprintf('Only %s outstanding on this line.', $outstanding + 0));

                        return;
                    }

                    if ((float) $value > $available) {
                        $fail(sprintf('Only %s available at this location.', $available + 0));
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
            'gi_date' => 'issue date',
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
    public function save(GoodsIssueService $issues): GoodsIssue
    {
        $data = $this->validate();

        $payload = GoodsIssueData::fromArray([
            'sales_order_id' => $this->order->id,
            'location_id' => $data['location_id'],
            'gi_date' => $data['gi_date'],
            'transaction_date' => $data['transaction_date'],
            'remarks' => $data['remarks'],
            'items' => $this->activeRows(),
        ]);

        return $this->issue === null
            ? $issues->create($this->order, $payload)
            : $issues->update($this->issue, $payload);
    }
}
