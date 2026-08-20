<?php

namespace App\Services;

use App\Data\GoodsReceiptData;
use App\Data\MovementLineData;
use App\Enums\DocumentAction;
use App\Enums\GoodsReceiptStatus;
use App\Enums\InventoryMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Goods receipt behaviour: receiving stock against a purchase order.
 *
 * A receipt is a plan while it is pending and a fact once completed - that is
 * the only moment inventory moves. Cancelling a completed receipt books the
 * same quantities back out, and both transitions push the purchase order
 * through PurchaseOrderService so the order status and the received
 * quantities always follow from the receipts rather than being set by hand.
 */
class GoodsReceiptService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly MaterialCostingService $costing,
        private readonly PurchaseOrderService $orders,
    ) {}

    /**
     * Draft a receipt against a purchase order.
     */
    public function create(PurchaseOrder $order, GoodsReceiptData $data): GoodsReceipt
    {
        if (! $order->status->allowsReceiving()) {
            throw BusinessRuleException::make('Goods receipt cannot be created for this purchase order.')
                ->redirectTo('purchase-orders.show', ['purchase_order' => $order->id]);
        }

        return DB::transaction(function () use ($order, $data): GoodsReceipt {
            $receipt = GoodsReceipt::create([
                ...$data->toHeaderColumns(),
                'purchase_order_id' => $order->id,
                'user_id' => Auth::id(),
                'status' => GoodsReceiptStatus::Pending,
            ]);

            $this->syncLines($receipt, $order, $data->lines);

            $receipt->recordLog(
                DocumentAction::Created,
                toStatus: GoodsReceiptStatus::Pending,
                remarks: 'Goods receipt created',
            );

            $order->recordLog(
                DocumentAction::ReceiptCreated,
                fromStatus: $order->status,
                toStatus: $order->status,
                remarks: "Goods receipt {$receipt->code} created",
            );

            return $receipt->refresh();
        });
    }

    /**
     * Update a pending receipt: quantities, location, dates and line details.
     */
    public function update(GoodsReceipt $receipt, GoodsReceiptData $data): GoodsReceipt
    {
        $this->guardEditable($receipt);

        return DB::transaction(function () use ($receipt, $data): GoodsReceipt {
            $order = $receipt->purchaseOrder;

            $receipt->update($data->toHeaderColumns());

            $this->syncLines($receipt, $order, $data->lines);

            $receipt->recordLog(
                DocumentAction::Updated,
                fromStatus: $receipt->status,
                toStatus: $receipt->status,
                remarks: 'Goods receipt updated',
            );

            return $receipt->refresh();
        });
    }

    /**
     * Delete a pending receipt and release the quantities it was holding.
     */
    public function delete(GoodsReceipt $receipt): void
    {
        if (! $receipt->status->allowsDeletion()) {
            throw BusinessRuleException::make('Only pending goods receipts can be deleted.')
                ->redirectTo('goods-receipts.show', ['goods_receipt' => $receipt->id]);
        }

        DB::transaction(function () use ($receipt): void {
            $order = $receipt->purchaseOrder;
            $code = $receipt->code;

            $receipt->recordLog(
                DocumentAction::Deleted,
                fromStatus: $receipt->status,
                remarks: 'Goods receipt deleted',
            );

            $receipt->items()->delete();
            $receipt->delete();

            if ($order !== null) {
                $order->recordLog(
                    DocumentAction::ReceiptDeleted,
                    fromStatus: $order->status,
                    toStatus: $order->status,
                    remarks: "Goods receipt {$code} deleted",
                );

                $this->orders->syncReceiptState($order);
            }
        });
    }

    /**
     * Complete the receipt: this is the point where stock exists.
     */
    public function complete(GoodsReceipt $receipt): GoodsReceipt
    {
        if (! $receipt->status->allowsCompletion()) {
            throw BusinessRuleException::make('Only pending goods receipts can be completed.');
        }

        $order = $receipt->purchaseOrder;

        if ($order === null || $order->status->isCancelled()) {
            throw BusinessRuleException::make('The purchase order for this goods receipt is cancelled.');
        }

        return DB::transaction(function () use ($receipt, $order): GoodsReceipt {
            $items = $receipt->items()->with('material')->get();

            if ($items->isEmpty()) {
                throw BusinessRuleException::make('This goods receipt has no items to receive.');
            }

            foreach ($items as $item) {
                if (Money::quantity($item->qty_to_receive) <= 0) {
                    continue;
                }

                $this->inventory->increase(
                    materialId: $item->material_id,
                    locationId: $receipt->location_id,
                    quantity: (float) $item->qty_to_receive,
                    type: InventoryMovementType::PurchaseReceipt,
                    reference: $receipt,
                    remarks: "Goods receipt {$receipt->code} completed",
                );
            }

            $receipt->update(['status' => GoodsReceiptStatus::Completed]);

            $receipt->recordLog(
                DocumentAction::Completed,
                fromStatus: GoodsReceiptStatus::Pending,
                toStatus: GoodsReceiptStatus::Completed,
                remarks: 'Goods receipt completed and inventory updated',
            );

            $this->costing->syncMany($items->pluck('material_id'));
            $this->orders->syncReceiptState($order);

            return $receipt->refresh();
        });
    }

    /**
     * Cancel the receipt, reversing its stock when it had already been
     * completed.
     *
     * @param  bool  $syncOrder  false while the purchase order is cancelling
     *                           itself and will refresh its own state after
     */
    public function cancel(GoodsReceipt $receipt, ?string $remarks = null, bool $syncOrder = true): GoodsReceipt
    {
        if (! $receipt->status->allowsCancellation()) {
            throw BusinessRuleException::make('This goods receipt cannot be cancelled.');
        }

        return DB::transaction(function () use ($receipt, $remarks, $syncOrder): GoodsReceipt {
            $from = $receipt->status;
            $order = $receipt->purchaseOrder;
            $items = $receipt->items()->with('material')->get();

            if ($from->affectsStock()) {
                foreach ($items as $item) {
                    if (Money::quantity($item->qty_to_receive) <= 0) {
                        continue;
                    }

                    // Strict: the reversal fails when the stock has since been
                    // issued, rather than silently clamping the balance to zero.
                    $this->inventory->decrease(
                        materialId: $item->material_id,
                        locationId: $receipt->location_id,
                        quantity: (float) $item->qty_to_receive,
                        type: InventoryMovementType::PurchaseReturn,
                        reference: $receipt,
                        remarks: $remarks ?? "Goods receipt {$receipt->code} cancelled - inventory reversed",
                    );
                }
            }

            $receipt->update(['status' => GoodsReceiptStatus::Cancelled]);

            $receipt->recordLog(
                DocumentAction::Cancelled,
                fromStatus: $from,
                toStatus: GoodsReceiptStatus::Cancelled,
                remarks: $remarks ?? ($from->affectsStock()
                    ? 'Goods receipt cancelled and inventory reversed'
                    : 'Goods receipt cancelled'),
            );

            if ($from->affectsStock()) {
                $this->costing->syncMany($items->pluck('material_id'));
            }

            if ($syncOrder && $order !== null) {
                $this->orders->syncReceiptState($order);
            }

            return $receipt->refresh();
        });
    }

    /**
     * Put a cancelled receipt back to pending so it can be corrected and
     * completed again.
     *
     * @param  bool  $syncOrder  false while the purchase order drives the revert
     */
    public function revert(GoodsReceipt $receipt, ?string $remarks = null, bool $syncOrder = true): GoodsReceipt
    {
        if (! $receipt->status->allowsRevert()) {
            throw BusinessRuleException::make('Only cancelled goods receipts can be reverted to pending.');
        }

        $order = $receipt->purchaseOrder;

        if ($syncOrder && $order !== null && $order->status->isCancelled()) {
            throw BusinessRuleException::make('Revert the purchase order first: it is currently cancelled.');
        }

        return DB::transaction(function () use ($receipt, $remarks, $syncOrder, $order): GoodsReceipt {
            $receipt->update(['status' => GoodsReceiptStatus::Pending]);

            $receipt->recordLog(
                DocumentAction::Reverted,
                fromStatus: GoodsReceiptStatus::Cancelled,
                toStatus: GoodsReceiptStatus::Pending,
                remarks: $remarks ?? 'Goods receipt reverted to pending',
            );

            if ($syncOrder && $order !== null) {
                $this->orders->syncReceiptState($order);
            }

            return $receipt->refresh();
        });
    }

    /**
     * Write the receipt lines, snapshotting the order line state at the time
     * of receiving and refusing to receive more than is outstanding.
     *
     * @param  array<int, MovementLineData>  $lines
     */
    private function syncLines(GoodsReceipt $receipt, PurchaseOrder $order, array $lines): void
    {
        $orderItems = $order->items()->with('material')->get()->keyBy('id');
        $outstanding = $this->orders->outstandingQuantities($order, ignoreReceiptId: $receipt->id);
        $existing = $receipt->items()->get()->keyBy('purchase_order_item_id');

        $keptItemIds = [];

        foreach ($lines as $line) {
            /** @var PurchaseOrderItem|null $orderItem */
            $orderItem = $orderItems->get($line->sourceItemId);

            if ($orderItem === null) {
                throw BusinessRuleException::make('One of the items does not belong to this purchase order.');
            }

            $quantity = Money::quantity($line->quantity);
            $limit = Money::quantity($outstanding[$orderItem->id] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            if ($quantity > $limit) {
                throw BusinessRuleException::make(sprintf(
                    'Quantity to receive for [%s] %s exceeds the outstanding %s.',
                    $orderItem->material?->code ?? $orderItem->material_id,
                    $orderItem->material?->name ?? '',
                    $limit + 0,
                ));
            }

            $columns = [
                'purchase_order_item_id' => $orderItem->id,
                'material_id' => $orderItem->material_id,
                'qty_ordered' => (float) $orderItem->qty_ordered,
                'qty_received' => (float) $orderItem->qty_received,
                'qty_to_receive' => $quantity,
                'qty_remaining' => Money::quantity(max(0, $limit - $quantity)),
                'unit_cost' => (float) $orderItem->unit_cost_after_discount,
                'serial_number' => $line->serialNumber,
                'batch_number' => $line->batchNumber,
                'remarks' => $line->remarks,
            ];

            $item = $existing->get($orderItem->id);

            if ($item instanceof GoodsReceiptItem) {
                $item->update($columns);
                $keptItemIds[] = $item->id;

                continue;
            }

            $keptItemIds[] = $receipt->items()->create($columns)->id;
        }

        if ($keptItemIds === []) {
            throw BusinessRuleException::make('A goods receipt needs at least one item with a quantity to receive.');
        }

        $receipt->items()->whereKeyNot($keptItemIds)->delete();
    }

    private function guardEditable(GoodsReceipt $receipt): void
    {
        if (! $receipt->status->allowsEditing()) {
            throw BusinessRuleException::make('Only pending goods receipts can be edited.')
                ->redirectTo('goods-receipts.show', ['goods_receipt' => $receipt->id]);
        }
    }
}
