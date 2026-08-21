<?php

namespace App\Services;

use App\Data\GoodsIssueData;
use App\Data\MovementLineData;
use App\Enums\DocumentAction;
use App\Enums\GoodsIssueStatus;
use App\Enums\InventoryMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Goods issue behaviour: shipping stock against a sales order.
 *
 * Mirrors GoodsReceiptService. A pending issue only reserves stock (the
 * reservation is what InventoryService::availableQuantity() subtracts);
 * completing it is what actually takes the quantity out of the location, and
 * the stock check happens there under a row lock, so two issues for the same
 * material can never both succeed on the same units.
 */
class GoodsIssueService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly MaterialCostingService $costing,
        private readonly SalesOrderService $orders,
    ) {}

    /**
     * Draft an issue against a sales order.
     */
    public function create(SalesOrder $order, GoodsIssueData $data): GoodsIssue
    {
        if (! $order->status->allowsIssuing()) {
            throw BusinessRuleException::make('Goods issue cannot be created for this sales order.')
                ->redirectTo('sales-orders.show', ['sales_order' => $order->id]);
        }

        return DB::transaction(function () use ($order, $data): GoodsIssue {
            $issue = GoodsIssue::create([
                ...$data->toHeaderColumns(),
                'sales_order_id' => $order->id,
                'user_id' => Auth::id(),
                'status' => GoodsIssueStatus::Pending,
            ]);

            $this->syncLines($issue, $order, $data->lines);

            $issue->recordLog(
                DocumentAction::Created,
                toStatus: GoodsIssueStatus::Pending,
                remarks: 'Goods issue created',
            );

            $order->recordLog(
                DocumentAction::IssueCreated,
                fromStatus: $order->status,
                toStatus: $order->status,
                remarks: "Goods issue {$issue->code} created",
            );

            return $issue->refresh();
        });
    }

    /**
     * Update a pending issue: quantities, location, dates and line details.
     */
    public function update(GoodsIssue $issue, GoodsIssueData $data): GoodsIssue
    {
        $this->guardEditable($issue);

        return DB::transaction(function () use ($issue, $data): GoodsIssue {
            $order = $issue->salesOrder;

            $issue->update($data->toHeaderColumns());

            $this->syncLines($issue, $order, $data->lines);

            $issue->recordLog(
                DocumentAction::Updated,
                fromStatus: $issue->status,
                toStatus: $issue->status,
                remarks: 'Goods issue updated',
            );

            return $issue->refresh();
        });
    }

    /**
     * Delete a pending issue and release the stock it was reserving.
     */
    public function delete(GoodsIssue $issue): void
    {
        if (! $issue->status->allowsDeletion()) {
            throw BusinessRuleException::make('Only pending goods issues can be deleted.')
                ->redirectTo('goods-issues.show', ['goods_issue' => $issue->id]);
        }

        DB::transaction(function () use ($issue): void {
            $order = $issue->salesOrder;
            $code = $issue->code;

            $issue->recordLog(
                DocumentAction::Deleted,
                fromStatus: $issue->status,
                remarks: 'Goods issue deleted',
            );

            $issue->items()->delete();
            $issue->delete();

            if ($order !== null) {
                $order->recordLog(
                    DocumentAction::IssueDeleted,
                    fromStatus: $order->status,
                    toStatus: $order->status,
                    remarks: "Goods issue {$code} deleted",
                );

                $this->orders->syncIssueState($order);
            }
        });
    }

    /**
     * Complete the issue: the point where stock leaves the warehouse.
     *
     * @throws \App\Exceptions\InsufficientStockException when a location cannot cover a line
     */
    public function complete(GoodsIssue $issue): GoodsIssue
    {
        if (! $issue->status->allowsCompletion()) {
            throw BusinessRuleException::make('Only pending goods issues can be completed.');
        }

        $order = $issue->salesOrder;

        if ($order === null || $order->status->isCancelled()) {
            throw BusinessRuleException::make('The sales order for this goods issue is cancelled.');
        }

        return DB::transaction(function () use ($issue, $order): GoodsIssue {
            $items = $issue->items()->with('material')->get();

            if ($items->isEmpty()) {
                throw BusinessRuleException::make('This goods issue has no items to ship.');
            }

            foreach ($items as $item) {
                if (Money::quantity($item->qty_to_ship) <= 0) {
                    continue;
                }

                // Locks the inventory row and refuses to go negative, so this
                // is also the concurrency guard for two simultaneous issues.
                $this->inventory->decrease(
                    materialId: $item->material_id,
                    locationId: $issue->location_id,
                    quantity: (float) $item->qty_to_ship,
                    type: InventoryMovementType::SalesIssue,
                    reference: $issue,
                    remarks: "Goods issue {$issue->code} completed",
                );
            }

            $issue->update(['status' => GoodsIssueStatus::Completed]);

            $issue->recordLog(
                DocumentAction::Completed,
                fromStatus: GoodsIssueStatus::Pending,
                toStatus: GoodsIssueStatus::Completed,
                remarks: 'Goods issue completed and inventory deducted',
            );

            $this->costing->syncMany($items->pluck('material_id'));
            $this->orders->syncIssueState($order);

            return $issue->refresh();
        });
    }

    /**
     * Cancel the issue, putting stock back when it had already shipped.
     *
     * @param  bool  $syncOrder  false while the sales order cancels itself
     */
    public function cancel(GoodsIssue $issue, ?string $remarks = null, bool $syncOrder = true): GoodsIssue
    {
        if (! $issue->status->allowsCancellation()) {
            throw BusinessRuleException::make('This goods issue cannot be cancelled.');
        }

        return DB::transaction(function () use ($issue, $remarks, $syncOrder): GoodsIssue {
            $from = $issue->status;
            $order = $issue->salesOrder;
            $items = $issue->items()->with('material')->get();

            if ($from->affectsStock()) {
                foreach ($items as $item) {
                    if (Money::quantity($item->qty_to_ship) <= 0) {
                        continue;
                    }

                    $this->inventory->increase(
                        materialId: $item->material_id,
                        locationId: $issue->location_id,
                        quantity: (float) $item->qty_to_ship,
                        type: InventoryMovementType::SalesReturn,
                        reference: $issue,
                        remarks: $remarks ?? "Goods issue {$issue->code} cancelled - inventory restored",
                    );
                }
            }

            $issue->update(['status' => GoodsIssueStatus::Cancelled]);

            $issue->recordLog(
                DocumentAction::Cancelled,
                fromStatus: $from,
                toStatus: GoodsIssueStatus::Cancelled,
                remarks: $remarks ?? ($from->affectsStock()
                    ? 'Goods issue cancelled and inventory restored'
                    : 'Goods issue cancelled'),
            );

            if ($from->affectsStock()) {
                $this->costing->syncMany($items->pluck('material_id'));
            }

            if ($syncOrder && $order !== null) {
                $this->orders->syncIssueState($order);
            }

            return $issue->refresh();
        });
    }

    /**
     * Put a cancelled issue back to pending.
     *
     * @param  bool  $syncOrder  false while the sales order drives the revert
     */
    public function revert(GoodsIssue $issue, ?string $remarks = null, bool $syncOrder = true): GoodsIssue
    {
        if (! $issue->status->allowsRevert()) {
            throw BusinessRuleException::make('Only cancelled goods issues can be reverted to pending.');
        }

        $order = $issue->salesOrder;

        if ($syncOrder && $order !== null && $order->status->isCancelled()) {
            throw BusinessRuleException::make('Revert the sales order first: it is currently cancelled.');
        }

        return DB::transaction(function () use ($issue, $remarks, $syncOrder, $order): GoodsIssue {
            $issue->update(['status' => GoodsIssueStatus::Pending]);

            $issue->recordLog(
                DocumentAction::Reverted,
                fromStatus: GoodsIssueStatus::Cancelled,
                toStatus: GoodsIssueStatus::Pending,
                remarks: $remarks ?? 'Goods issue reverted to pending',
            );

            if ($syncOrder && $order !== null) {
                $this->orders->syncIssueState($order);
            }

            return $issue->refresh();
        });
    }

    /**
     * Write the issue lines, checking both the order outstanding quantity and
     * the stock available at the chosen location.
     *
     * @param  array<int, MovementLineData>  $lines
     */
    private function syncLines(GoodsIssue $issue, SalesOrder $order, array $lines): void
    {
        $orderItems = $order->items()->with('material')->get()->keyBy('id');
        $outstanding = $this->orders->outstandingQuantities($order, ignoreIssueId: $issue->id);
        $existing = $issue->items()->get()->keyBy('sales_order_item_id');

        $keptItemIds = [];

        foreach ($lines as $line) {
            /** @var SalesOrderItem|null $orderItem */
            $orderItem = $orderItems->get($line->sourceItemId);

            if ($orderItem === null) {
                throw BusinessRuleException::make('One of the items does not belong to this sales order.');
            }

            $quantity = Money::quantity($line->quantity);

            if ($quantity <= 0) {
                continue;
            }

            $limit = Money::quantity($outstanding[$orderItem->id] ?? 0);

            if ($quantity > $limit) {
                throw BusinessRuleException::make(sprintf(
                    'Quantity to ship for [%s] %s exceeds the outstanding %s.',
                    $orderItem->material?->code ?? $orderItem->material_id,
                    $orderItem->material?->name ?? '',
                    $limit + 0,
                ));
            }

            $this->guardStockAvailable($issue, $orderItem, $quantity);

            $columns = [
                'sales_order_item_id' => $orderItem->id,
                'material_id' => $orderItem->material_id,
                'qty_ordered' => (float) $orderItem->qty_ordered,
                'qty_shipped' => (float) $orderItem->qty_shipped,
                'qty_to_ship' => $quantity,
                'qty_remaining' => Money::quantity(max(0, $limit - $quantity)),
                'unit_price' => (float) $orderItem->unit_price_after_discount,
                'serial_number' => $line->serialNumber,
                'batch_number' => $line->batchNumber,
                'remarks' => $line->remarks,
            ];

            $item = $existing->get($orderItem->id);

            if ($item instanceof GoodsIssueItem) {
                $item->update($columns);
                $keptItemIds[] = $item->id;

                continue;
            }

            $keptItemIds[] = $issue->items()->create($columns)->id;
        }

        if ($keptItemIds === []) {
            throw BusinessRuleException::make('A goods issue needs at least one item with a quantity to ship.');
        }

        $issue->items()->whereKeyNot($keptItemIds)->delete();
    }

    /**
     * Refuse to reserve more than the location can still promise.
     */
    private function guardStockAvailable(GoodsIssue $issue, SalesOrderItem $orderItem, float $quantity): void
    {
        $available = $this->inventory->availableQuantity(
            materialId: $orderItem->material_id,
            locationId: $issue->location_id,
            ignoreGoodsIssueId: $issue->id,
        );

        if ($quantity > $available) {
            throw BusinessRuleException::make(sprintf(
                'Insufficient stock for [%s] %s at %s. Available: %s, Required: %s.',
                $orderItem->material?->code ?? $orderItem->material_id,
                $orderItem->material?->name ?? '',
                $issue->location?->name ?? 'the selected location',
                $available + 0,
                $quantity + 0,
            ));
        }
    }

    private function guardEditable(GoodsIssue $issue): void
    {
        if (! $issue->status->allowsEditing()) {
            throw BusinessRuleException::make('Only pending goods issues can be edited.')
                ->redirectTo('goods-issues.show', ['goods_issue' => $issue->id]);
        }
    }
}
