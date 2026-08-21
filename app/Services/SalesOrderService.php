<?php

namespace App\Services;

use App\Data\OrderLineData;
use App\Data\SalesOrderData;
use App\Enums\DocumentAction;
use App\Enums\GoodsIssueStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
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
 * All sales order behaviour: pricing, the status flow, and the knock-on
 * effects on goods issues.
 *
 * Deliberately mirrors PurchaseOrderService method for method - receiving
 * becomes shipping - so a change to one flow is easy to reflect in the other.
 */
class SalesOrderService
{
    public function __construct(
        private readonly LineCalculator $lineCalculator,
        private readonly DocumentTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * Create a draft sales order with its lines and charges.
     */
    public function create(SalesOrderData $data): SalesOrder
    {
        $this->guardHasLines($data);

        return DB::transaction(function () use ($data): SalesOrder {
            $order = SalesOrder::create([
                ...$data->toHeaderColumns(),
                'user_id' => Auth::id(),
                'status' => SalesOrderStatus::Draft,
            ]);

            $this->applyTotals($order, $data, $this->syncLines($order, $data->lines));

            $order->recordLog(
                DocumentAction::Created,
                toStatus: SalesOrderStatus::Draft,
                remarks: 'Sales order created',
            );

            return $order->refresh();
        });
    }

    /**
     * Update a draft sales order, reusing the existing lines per material so
     * the goods issue lines that reference them survive the edit.
     */
    public function update(SalesOrder $order, SalesOrderData $data): SalesOrder
    {
        $this->guardEditable($order);
        $this->guardHasLines($data);

        return DB::transaction(function () use ($order, $data): SalesOrder {
            $from = $order->status;

            $order->update($data->toHeaderColumns());

            $this->applyTotals($order, $data, $this->syncLines($order, $data->lines));

            $order->recordLog(
                DocumentAction::Updated,
                fromStatus: $from,
                toStatus: $order->status,
                remarks: 'Sales order updated',
            );

            return $order->refresh();
        });
    }

    /**
     * Delete a draft sales order together with its not-yet-shipped issues.
     */
    public function delete(SalesOrder $order): void
    {
        if (! $order->status->allowsDeletion()) {
            throw BusinessRuleException::make('Only draft sales orders can be deleted.')
                ->redirectTo('sales-orders.show', ['sales_order' => $order->id]);
        }

        $completed = $order->goodsIssues()
            ->where('status', GoodsIssueStatus::Completed->value)
            ->pluck('code');

        if ($completed->isNotEmpty()) {
            throw BusinessRuleException::make(sprintf(
                'Cannot delete: goods issue(s) %s already shipped stock against this order.',
                $completed->implode(', '),
            ))->redirectTo('sales-orders.show', ['sales_order' => $order->id]);
        }

        DB::transaction(function () use ($order): void {
            $order->goodsIssues()->get()->each(fn (GoodsIssue $issue) => $issue->delete());

            $order->recordLog(
                DocumentAction::Deleted,
                fromStatus: $order->status,
                remarks: 'Sales order deleted',
            );

            $order->delete();
        });
    }

    /**
     * Confirm the order to the customer: from here on issues may be raised.
     */
    public function post(SalesOrder $order): SalesOrder
    {
        if (! $order->status->allowsPosting()) {
            throw BusinessRuleException::make('Only draft sales orders can be posted.');
        }

        if ($order->items()->doesntExist()) {
            throw BusinessRuleException::make('A sales order needs at least one item before it can be posted.');
        }

        return DB::transaction(function () use ($order): SalesOrder {
            $from = $order->status;

            $order->update(['status' => SalesOrderStatus::Posted]);

            $order->recordLog(
                DocumentAction::Posted,
                fromStatus: $from,
                toStatus: SalesOrderStatus::Posted,
                remarks: 'Sales order posted',
            );

            return $order->refresh();
        });
    }

    /**
     * Cancel the order and every issue raised against it; completed issues put
     * their stock back before the order closes.
     */
    public function cancel(SalesOrder $order): SalesOrder
    {
        if (! $order->status->allowsCancellation()) {
            throw BusinessRuleException::make('This sales order is already cancelled.');
        }

        return DB::transaction(function () use ($order): SalesOrder {
            $from = $order->status;

            $issues = $order->goodsIssues()
                ->where('status', '!=', GoodsIssueStatus::Cancelled->value)
                ->get();

            foreach ($issues as $issue) {
                $this->issues()->cancel(
                    issue: $issue,
                    remarks: "Cancelled via sales order {$order->code} cancellation",
                    syncOrder: false,
                );
            }

            $order->update(['status' => SalesOrderStatus::Cancelled]);

            $order->recordLog(
                DocumentAction::Cancelled,
                fromStatus: $from,
                toStatus: SalesOrderStatus::Cancelled,
                remarks: $issues->isEmpty()
                    ? 'Sales order cancelled'
                    : sprintf('Sales order cancelled together with %d goods issue(s)', $issues->count()),
            );

            return $this->syncIssueState($order->refresh(), log: false);
        });
    }

    /**
     * Send the order back to draft, provided no stock has left the warehouse
     * against it.
     */
    public function revert(SalesOrder $order): SalesOrder
    {
        if (! $order->status->allowsRevert()) {
            throw BusinessRuleException::make('Only posted or cancelled sales orders can be reverted to draft.');
        }

        $completed = $order->goodsIssues()
            ->where('status', GoodsIssueStatus::Completed->value)
            ->pluck('code');

        if ($completed->isNotEmpty()) {
            throw BusinessRuleException::make(sprintf(
                'Cannot revert: goods issue(s) %s already shipped stock. Cancel them first.',
                $completed->implode(', '),
            ));
        }

        return DB::transaction(function () use ($order): SalesOrder {
            $from = $order->status;

            $restored = $order->goodsIssues()
                ->where('status', GoodsIssueStatus::Cancelled->value)
                ->get();

            foreach ($restored as $issue) {
                $this->issues()->revert(
                    issue: $issue,
                    remarks: "Reverted to pending via sales order {$order->code} revert",
                    syncOrder: false,
                );
            }

            $order->update(['status' => SalesOrderStatus::Draft]);

            $order->recordLog(
                DocumentAction::Reverted,
                fromStatus: $from,
                toStatus: SalesOrderStatus::Draft,
                remarks: $restored->isEmpty()
                    ? 'Sales order reverted to draft'
                    : sprintf('Sales order reverted to draft, %d goods issue(s) back to pending', $restored->count()),
            );

            return $this->syncIssueState($order->refresh(), log: false);
        });
    }

    /**
     * Recompute `qty_shipped` on every line from the completed issues and
     * derive the shipping status from it. Draft and cancelled orders keep
     * their status; the audit entry is only written on a real status change.
     */
    public function syncIssueState(SalesOrder $order, bool $log = true): SalesOrder
    {
        return DB::transaction(function () use ($order, $log): SalesOrder {
            $shipped = $this->shippedQuantitiesByLine($order);

            foreach ($order->items()->get() as $item) {
                $quantity = Money::quantity($shipped[$item->id] ?? 0);

                if (! Money::quantityEquals($item->qty_shipped, $quantity)) {
                    $item->update(['qty_shipped' => $quantity]);
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
                    remarks: "Sales order status recalculated to {$target->label()}",
                );
            }

            return $order->refresh();
        });
    }

    /**
     * Quantity that may still be put on a new issue for each line, pending
     * issues included, keyed by sales order item id.
     *
     * @return array<int, float>
     */
    public function outstandingQuantities(SalesOrder $order, ?int $ignoreIssueId = null): array
    {
        $reserved = GoodsIssueItem::query()
            ->whereIn('sales_order_item_id', $order->items()->select('id'))
            ->whereHas('goodsIssue', function (Builder $query) use ($ignoreIssueId): void {
                $query->where('status', GoodsIssueStatus::Pending->value)
                    ->when($ignoreIssueId, fn (Builder $q) => $q->whereKeyNot($ignoreIssueId));
            })
            ->groupBy('sales_order_item_id')
            ->selectRaw('sales_order_item_id, COALESCE(SUM(qty_to_ship), 0) as reserved')
            ->pluck('reserved', 'sales_order_item_id');

        return $order->items()
            ->get()
            ->mapWithKeys(fn (SalesOrderItem $item): array => [
                $item->id => Money::quantity(max(0,
                    (float) $item->qty_ordered
                    - (float) $item->qty_shipped
                    - (float) ($reserved[$item->id] ?? 0)
                )),
            ])
            ->all();
    }

    /**
     * Status implied by the shipped quantities, or null when the current
     * status is outside the shipping flow (draft, cancelled).
     */
    private function deriveStatus(SalesOrder $order): ?SalesOrderStatus
    {
        if (! in_array($order->status, SalesOrderStatus::LIVE, true)) {
            return null;
        }

        $items = $order->items;

        if ($items->isEmpty()) {
            return SalesOrderStatus::Posted;
        }

        $fullyShipped = $items->every(
            fn (SalesOrderItem $item): bool => (float) $item->qty_shipped >= (float) $item->qty_ordered
        );

        if ($fullyShipped) {
            return SalesOrderStatus::FullyShipped;
        }

        $anyShipped = $items->contains(fn (SalesOrderItem $item): bool => (float) $item->qty_shipped > 0);

        return $anyShipped ? SalesOrderStatus::PartiallyShipped : SalesOrderStatus::Posted;
    }

    /**
     * Completed issue quantities per sales order item.
     *
     * @return array<int, float>
     */
    private function shippedQuantitiesByLine(SalesOrder $order): array
    {
        return GoodsIssueItem::query()
            ->whereIn('sales_order_item_id', $order->items()->select('id'))
            ->whereHas('goodsIssue', fn (Builder $query) => $query
                ->where('sales_order_id', $order->id)
                ->where('status', GoodsIssueStatus::Completed->value))
            ->groupBy('sales_order_item_id')
            ->selectRaw('sales_order_item_id, COALESCE(SUM(qty_to_ship), 0) as shipped')
            ->pluck('shipped', 'sales_order_item_id')
            ->map(fn (mixed $quantity): float => Money::quantity($quantity))
            ->all();
    }

    /**
     * @param  array<int, OrderLineData>  $lines
     * @return array<int, LineTotals>
     */
    private function syncLines(SalesOrder $order, array $lines): array
    {
        /** @var Collection<int, SalesOrderItem> $existing */
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
                'unit_price' => $computed->unitAmount,
                'unit_price_after_discount' => $computed->unitAmountAfterDiscount,
                'remarks' => $line->remarks,
            ];

            $item = empty($reusable[$line->materialId])
                ? null
                : array_shift($reusable[$line->materialId]);

            if ($item instanceof SalesOrderItem) {
                $this->guardQuantityNotBelowShipped($item, $computed->quantity);

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
     * @param  Collection<int, SalesOrderItem>  $removed
     */
    private function deleteRemovedLines(Collection $removed): void
    {
        foreach ($removed as $item) {
            $referenced = GoodsIssueItem::query()
                ->where('sales_order_item_id', $item->id)
                ->exists();

            if ($referenced) {
                throw BusinessRuleException::make(sprintf(
                    'Item [%s] %s cannot be removed because a goods issue already refers to it.',
                    $item->material?->code ?? $item->material_id,
                    $item->material?->name ?? '',
                ));
            }

            $item->delete();
        }
    }

    private function guardQuantityNotBelowShipped(SalesOrderItem $item, float $quantity): void
    {
        if ((float) $item->qty_shipped > 0 && $quantity < (float) $item->qty_shipped) {
            throw BusinessRuleException::make(sprintf(
                'Ordered quantity for [%s] %s cannot be lower than the %s already shipped.',
                $item->material?->code ?? $item->material_id,
                $item->material?->name ?? '',
                Money::quantity($item->qty_shipped) + 0,
            ));
        }
    }

    /**
     * @param  array<int, LineTotals>  $lineTotals
     */
    private function applyTotals(SalesOrder $order, SalesOrderData $data, array $lineTotals): DocumentTotals
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

    private function guardEditable(SalesOrder $order): void
    {
        if (! $order->status->allowsEditing()) {
            throw BusinessRuleException::make('Only draft sales orders can be edited.')
                ->redirectTo('sales-orders.show', ['sales_order' => $order->id]);
        }
    }

    private function guardHasLines(SalesOrderData $data): void
    {
        if ($data->lines === []) {
            throw BusinessRuleException::make('A sales order needs at least one item.');
        }
    }

    /**
     * Resolved lazily to avoid a constructor cycle with GoodsIssueService.
     */
    private function issues(): GoodsIssueService
    {
        return app(GoodsIssueService::class);
    }
}
