<?php

namespace App\Services;

use App\Data\OrderLineData;
use App\Data\PurchaseOrderData;
use App\Enums\DocumentAction;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\Support\DocumentTotals;
use App\Services\Support\DocumentTotalsCalculator;
use App\Services\Support\LineCalculator;
use App\Services\Support\LineTotals;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * All purchase order behaviour: pricing, the status flow, and the knock-on
 * effects on goods receipts.
 *
 * The controller is left with request -> data object -> service -> redirect,
 * which is also what lets the seeders build sample orders through exactly the
 * same code path the UI uses.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly LineCalculator $lineCalculator,
        private readonly DocumentTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * Create a draft purchase order with its lines and charges.
     */
    public function create(PurchaseOrderData $data): PurchaseOrder
    {
        $this->guardHasLines($data);

        return DB::transaction(function () use ($data): PurchaseOrder {
            $order = PurchaseOrder::create([
                ...$data->toHeaderColumns(),
                'user_id' => Auth::id(),
                'status' => PurchaseOrderStatus::Draft,
            ]);

            $this->applyTotals($order, $data, $this->syncLines($order, $data->lines));

            $order->recordLog(
                DocumentAction::Created,
                toStatus: PurchaseOrderStatus::Draft,
                remarks: 'Purchase order created',
            );

            return $order->refresh();
        });
    }

    /**
     * Update a draft purchase order.
     *
     * Lines are matched to the existing rows by material so quantities and
     * prices can be corrected without destroying the goods receipt lines that
     * point at them.
     */
    public function update(PurchaseOrder $order, PurchaseOrderData $data): PurchaseOrder
    {
        $this->guardEditable($order);
        $this->guardHasLines($data);

        return DB::transaction(function () use ($order, $data): PurchaseOrder {
            $from = $order->status;

            $order->update($data->toHeaderColumns());

            $this->applyTotals($order, $data, $this->syncLines($order, $data->lines));

            $order->recordLog(
                DocumentAction::Updated,
                fromStatus: $from,
                toStatus: $order->status,
                remarks: 'Purchase order updated',
            );

            return $order->refresh();
        });
    }

    /**
     * Delete a draft purchase order.
     *
     * Its receipts are soft deleted with it so that restoring the order brings
     * the whole document back; receipts that already booked stock block the
     * deletion outright.
     */
    public function delete(PurchaseOrder $order): void
    {
        if (! $order->status->allowsDeletion()) {
            throw BusinessRuleException::make('Only draft purchase orders can be deleted.')
                ->redirectTo('purchase-orders.show', ['purchase_order' => $order->id]);
        }

        $completed = $order->goodsReceipts()
            ->where('status', GoodsReceiptStatus::Completed->value)
            ->pluck('code');

        if ($completed->isNotEmpty()) {
            throw BusinessRuleException::make(sprintf(
                'Cannot delete: goods receipt(s) %s already received stock against this order.',
                $completed->implode(', '),
            ))->redirectTo('purchase-orders.show', ['purchase_order' => $order->id]);
        }

        DB::transaction(function () use ($order): void {
            $order->goodsReceipts()->get()->each(fn (GoodsReceipt $receipt) => $receipt->delete());

            $order->recordLog(
                DocumentAction::Deleted,
                fromStatus: $order->status,
                remarks: 'Purchase order deleted',
            );

            $order->delete();
        });
    }

    /**
     * Release the order to the vendor: from here on receipts may be raised.
     */
    public function post(PurchaseOrder $order): PurchaseOrder
    {
        if (! $order->status->allowsPosting()) {
            throw BusinessRuleException::make('Only draft purchase orders can be posted.');
        }

        if ($order->items()->doesntExist()) {
            throw BusinessRuleException::make('A purchase order needs at least one item before it can be posted.');
        }

        return DB::transaction(function () use ($order): PurchaseOrder {
            $from = $order->status;

            $order->update(['status' => PurchaseOrderStatus::Posted]);

            $order->recordLog(
                DocumentAction::Posted,
                fromStatus: $from,
                toStatus: PurchaseOrderStatus::Posted,
                remarks: 'Purchase order posted',
            );

            return $order->refresh();
        });
    }

    /**
     * Cancel the order and everything raised against it. Completed receipts
     * are reversed out of inventory first, which is why this delegates to the
     * goods receipt service instead of touching stock itself.
     */
    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (! $order->status->allowsCancellation()) {
            throw BusinessRuleException::make('This purchase order is already cancelled.');
        }

        return DB::transaction(function () use ($order): PurchaseOrder {
            $from = $order->status;

            $receipts = $order->goodsReceipts()
                ->where('status', '!=', GoodsReceiptStatus::Cancelled->value)
                ->get();

            foreach ($receipts as $receipt) {
                $this->receipts()->cancel(
                    receipt: $receipt,
                    remarks: "Cancelled via purchase order {$order->code} cancellation",
                    syncOrder: false,
                );
            }

            $order->update(['status' => PurchaseOrderStatus::Cancelled]);

            $order->recordLog(
                DocumentAction::Cancelled,
                fromStatus: $from,
                toStatus: PurchaseOrderStatus::Cancelled,
                remarks: $receipts->isEmpty()
                    ? 'Purchase order cancelled'
                    : sprintf('Purchase order cancelled together with %d goods receipt(s)', $receipts->count()),
            );

            // Quantities only - a cancelled order keeps its status.
            return $this->syncReceiptState($order->refresh(), log: false);
        });
    }

    /**
     * Send the order back to draft.
     *
     * Only allowed while no stock is booked against it, otherwise the receipts
     * would describe a document that no longer matches them.
     */
    public function revert(PurchaseOrder $order): PurchaseOrder
    {
        if (! $order->status->allowsRevert()) {
            throw BusinessRuleException::make('Only posted or cancelled purchase orders can be reverted to draft.');
        }

        $completed = $order->goodsReceipts()
            ->where('status', GoodsReceiptStatus::Completed->value)
            ->pluck('code');

        if ($completed->isNotEmpty()) {
            throw BusinessRuleException::make(sprintf(
                'Cannot revert: goods receipt(s) %s already received stock. Cancel them first.',
                $completed->implode(', '),
            ));
        }

        return DB::transaction(function () use ($order): PurchaseOrder {
            $from = $order->status;

            $restored = $order->goodsReceipts()
                ->where('status', GoodsReceiptStatus::Cancelled->value)
                ->get();

            foreach ($restored as $receipt) {
                $this->receipts()->revert(
                    receipt: $receipt,
                    remarks: "Reverted to pending via purchase order {$order->code} revert",
                    syncOrder: false,
                );
            }

            $order->update(['status' => PurchaseOrderStatus::Draft]);

            $order->recordLog(
                DocumentAction::Reverted,
                fromStatus: $from,
                toStatus: PurchaseOrderStatus::Draft,
                remarks: $restored->isEmpty()
                    ? 'Purchase order reverted to draft'
                    : sprintf('Purchase order reverted to draft, %d goods receipt(s) back to pending', $restored->count()),
            );

            return $this->syncReceiptState($order->refresh(), log: false);
        });
    }

    /**
     * Recompute `qty_received` on every line from the completed receipts and
     * derive the receiving status from it.
     *
     * Draft and cancelled orders keep their status - only the quantities are
     * refreshed - and the audit entry is only written when the status really
     * moved, which keeps the trail readable.
     */
    public function syncReceiptState(PurchaseOrder $order, bool $log = true): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $log): PurchaseOrder {
            $received = $this->receivedQuantitiesByLine($order);

            foreach ($order->items()->get() as $item) {
                $quantity = Money::quantity($received[$item->id] ?? 0);

                if (! Money::quantityEquals($item->qty_received, $quantity)) {
                    $item->update(['qty_received' => $quantity]);
                }
            }

            $order->refresh()->load('items');

            $from = $order->status;
            $target = $this->deriveStatus($order);

            if ($target === null || $target === $from) {
                return $order;
            }

            $order->update(['status' => $target]);

            if ($log) {
                $order->recordLog(
                    DocumentAction::StatusRecalculated,
                    fromStatus: $from,
                    toStatus: $target,
                    remarks: "Purchase order status recalculated to {$target->label()}",
                );
            }

            return $order->refresh();
        });
    }

    /**
     * Quantity that may still be put on a new receipt for each line, pending
     * receipts included, keyed by purchase order item id.
     *
     * @return array<int, float>
     */
    public function outstandingQuantities(PurchaseOrder $order, ?int $ignoreReceiptId = null): array
    {
        $reserved = GoodsReceiptItem::query()
            ->whereIn('purchase_order_item_id', $order->items()->select('id'))
            ->whereHas('goodsReceipt', function (Builder $query) use ($ignoreReceiptId): void {
                $query->where('status', GoodsReceiptStatus::Pending->value)
                    ->when($ignoreReceiptId, fn (Builder $q) => $q->whereKeyNot($ignoreReceiptId));
            })
            ->groupBy('purchase_order_item_id')
            ->selectRaw('purchase_order_item_id, COALESCE(SUM(qty_to_receive), 0) as reserved')
            ->pluck('reserved', 'purchase_order_item_id');

        return $order->items()
            ->get()
            ->mapWithKeys(fn (PurchaseOrderItem $item): array => [
                $item->id => Money::quantity(max(0,
                    (float) $item->qty_ordered
                    - (float) $item->qty_received
                    - (float) ($reserved[$item->id] ?? 0)
                )),
            ])
            ->all();
    }

    /**
     * Status implied by the received quantities, or null when the current
     * status is not part of the receiving flow (draft, cancelled).
     */
    private function deriveStatus(PurchaseOrder $order): ?PurchaseOrderStatus
    {
        if (! in_array($order->status, PurchaseOrderStatus::LIVE, true)) {
            return null;
        }

        $items = $order->items;

        if ($items->isEmpty()) {
            return PurchaseOrderStatus::Posted;
        }

        $fullyReceived = $items->every(
            fn (PurchaseOrderItem $item): bool => (float) $item->qty_received >= (float) $item->qty_ordered
        );

        if ($fullyReceived) {
            return PurchaseOrderStatus::FullyReceived;
        }

        $anyReceived = $items->contains(fn (PurchaseOrderItem $item): bool => (float) $item->qty_received > 0);

        return $anyReceived ? PurchaseOrderStatus::PartiallyReceived : PurchaseOrderStatus::Posted;
    }

    /**
     * Completed receipt quantities per purchase order item.
     *
     * @return array<int, float>
     */
    private function receivedQuantitiesByLine(PurchaseOrder $order): array
    {
        return GoodsReceiptItem::query()
            ->whereIn('purchase_order_item_id', $order->items()->select('id'))
            ->whereHas('goodsReceipt', fn (Builder $query) => $query
                ->where('purchase_order_id', $order->id)
                ->where('status', GoodsReceiptStatus::Completed->value))
            ->groupBy('purchase_order_item_id')
            ->selectRaw('purchase_order_item_id, COALESCE(SUM(qty_to_receive), 0) as received')
            ->pluck('received', 'purchase_order_item_id')
            ->map(fn (mixed $quantity): float => Money::quantity($quantity))
            ->all();
    }

    /**
     * Write the incoming lines onto the order.
     *
     * Existing rows are reused per material so the goods receipt lines that
     * reference them survive an edit; rows dropped from the document are only
     * deleted when nothing has been received against them.
     *
     * @param  array<int, OrderLineData>  $lines
     * @return array<int, LineTotals>
     */
    private function syncLines(PurchaseOrder $order, array $lines): array
    {
        /** @var Collection<int, PurchaseOrderItem> $existing */
        $existing = $order->items()->with('material')->get();

        $reusable = $existing->groupBy('material_id')
            ->map(fn (Collection $items): array => $items->sortBy('line_number')->values()->all())
            ->all();

        $keptIds = [];
        $totals = [];

        foreach ($lines as $index => $line) {
            $computed = $this->lineCalculator->calculate(
                quantity: $line->quantity,
                unitAmount: $line->unitAmount,
                discountType: $line->discountType,
                discountAmount: $line->discountAmount,
                isVatable: $line->isVatable,
                vatType: $line->vatType,
                vatRate: $line->vatRate,
            );

            $totals[$index] = $computed;

            $columns = [
                ...$computed->toColumns(),
                'material_id' => $line->materialId,
                'line_number' => $index + 1,
                'qty_ordered' => $computed->quantity,
                'unit_cost' => $computed->unitAmount,
                'unit_cost_after_discount' => $computed->unitAmountAfterDiscount,
                'remarks' => $line->remarks,
            ];

            $item = empty($reusable[$line->materialId])
                ? null
                : array_shift($reusable[$line->materialId]);

            if ($item instanceof PurchaseOrderItem) {
                $this->guardQuantityNotBelowReceived($item, $computed->quantity);

                $item->update($columns);
                $keptIds[] = $item->id;

                continue;
            }

            $keptIds[] = $order->items()->create($columns)->id;
        }

        $this->deleteRemovedLines($existing->whereNotIn('id', $keptIds));

        return $totals;
    }

    /**
     * Drop lines that are no longer on the document, refusing to remove one a
     * goods receipt already refers to.
     *
     * @param  Collection<int, PurchaseOrderItem>  $removed
     */
    private function deleteRemovedLines(Collection $removed): void
    {
        foreach ($removed as $item) {
            $referenced = GoodsReceiptItem::query()
                ->where('purchase_order_item_id', $item->id)
                ->exists();

            if ($referenced) {
                throw BusinessRuleException::make(sprintf(
                    'Item [%s] %s cannot be removed because a goods receipt already refers to it.',
                    $item->material?->code ?? $item->material_id,
                    $item->material?->name ?? '',
                ));
            }

            $item->delete();
        }
    }

    private function guardQuantityNotBelowReceived(PurchaseOrderItem $item, float $quantity): void
    {
        if ((float) $item->qty_received > 0 && $quantity < (float) $item->qty_received) {
            throw BusinessRuleException::make(sprintf(
                'Ordered quantity for [%s] %s cannot be lower than the %s already received.',
                $item->material?->code ?? $item->material_id,
                $item->material?->name ?? '',
                Money::quantity($item->qty_received) + 0,
            ));
        }
    }

    /**
     * Rebuild the charge rows and write the header totals.
     *
     * @param  array<int, LineTotals>  $lineTotals
     */
    private function applyTotals(PurchaseOrder $order, PurchaseOrderData $data, array $lineTotals): DocumentTotals
    {
        $totals = $this->totalsCalculator->compute(
            lines: $lineTotals,
            headerDiscountType: $data->discountType,
            headerDiscountAmount: $data->discountAmount,
            charges: $data->charges,
        );

        $order->charges()->delete();

        foreach ($data->charges as $index => $charge) {
            $order->charges()->create($charge->toColumns($totals->amountForCharge($index)));
        }

        $order->update($totals->toColumns());

        return $totals;
    }

    private function guardEditable(PurchaseOrder $order): void
    {
        if (! $order->status->allowsEditing()) {
            throw BusinessRuleException::make('Only draft purchase orders can be edited.')
                ->redirectTo('purchase-orders.show', ['purchase_order' => $order->id]);
        }
    }

    private function guardHasLines(PurchaseOrderData $data): void
    {
        if ($data->lines === []) {
            throw BusinessRuleException::make('A purchase order needs at least one item.');
        }
    }

    /**
     * Resolved lazily: the receipt service depends on this service to sync the
     * order state, so constructor injection would form a cycle.
     */
    private function receipts(): GoodsReceiptService
    {
        return app(GoodsReceiptService::class);
    }
}
