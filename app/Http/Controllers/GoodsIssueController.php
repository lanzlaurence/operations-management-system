<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoodsIssueRequest;
use App\Http\Requests\UpdateGoodsIssueRequest;
use App\Models\GoodsIssue;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\GoodsIssueService;
use App\Services\InventoryService;
use App\Services\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP entry points for goods issues.
 *
 * Stock only moves in GoodsIssueService::complete()/cancel(); this class
 * authorizes, shapes the payload for the screens and redirects.
 */
class GoodsIssueController extends Controller
{
    public function __construct(
        private readonly GoodsIssueService $issues,
        private readonly SalesOrderService $orders,
        private readonly InventoryService $inventory,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', GoodsIssue::class);

        return Inertia::render('sales/goods-issue/index', [
            'goodsIssues' => GoodsIssue::query()
                ->with(['salesOrder.customer', 'location:id,code,name', 'user:id,name'])
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Shipping form for one sales order, with the outstanding quantity per
     * line and the available stock per location resolved up front.
     */
    public function create(SalesOrder $salesOrder): Response|RedirectResponse
    {
        $this->authorize('create', GoodsIssue::class);

        if (! $salesOrder->canCreateGi()) {
            return redirect()
                ->route('sales-orders.show', $salesOrder->id)
                ->with('error', 'Goods issue cannot be created for this sales order.');
        }

        $salesOrder->load([
            'customer',
            'items.material.brand',
            'items.material.category',
            'items.material.uom',
        ]);

        $outstanding = $this->orders->outstandingQuantities($salesOrder);

        return Inertia::render('sales/goods-issue/create', [
            'salesOrder' => [
                ...$salesOrder->toArray(),
                'items' => $salesOrder->items
                    ->map(fn (SalesOrderItem $item): array => [
                        ...$item->toArray(),
                        'qty_remaining' => $outstanding[$item->id] ?? 0,
                    ])
                    ->all(),
            ],
            'locations' => Location::query()->orderBy('name')->get(['id', 'code', 'name']),
            'inventoryMap' => $this->availableStockMap($salesOrder),
        ]);
    }

    public function store(StoreGoodsIssueRequest $request): RedirectResponse
    {
        $data = $request->toData();
        $order = SalesOrder::findOrFail($data->salesOrderId);

        $issue = $this->issues->create($order, $data);

        return redirect()
            ->route('goods-issues.show', $issue->id)
            ->with('success', "Goods issue {$issue->code} created successfully.");
    }

    public function show(GoodsIssue $goodsIssue): Response
    {
        $this->authorize('view', $goodsIssue);

        $goodsIssue->load([
            'salesOrder.customer',
            'location',
            'user:id,name',
            'items.material.uom',
            'items.salesOrderItem',
            'logs.user:id,name',
        ]);

        return Inertia::render('sales/goods-issue/show', [
            'goodsIssue' => $goodsIssue,
            'can' => $goodsIssue->actionFlags(),
        ]);
    }

    public function edit(GoodsIssue $goodsIssue): Response|RedirectResponse
    {
        $this->authorize('update', $goodsIssue);

        if (! $goodsIssue->canBeEdited()) {
            return redirect()
                ->route('goods-issues.show', $goodsIssue->id)
                ->with('error', 'Only pending goods issues can be edited.');
        }

        $goodsIssue->load([
            'salesOrder.customer',
            'salesOrder.items.material',
            'location',
            'items.material',
            'items.salesOrderItem',
        ]);

        return Inertia::render('sales/goods-issue/edit', [
            'goodsIssue' => $goodsIssue,
            'locations' => Location::query()->orderBy('name')->get(['id', 'code', 'name']),
            'outstanding' => $this->orders->outstandingQuantities(
                $goodsIssue->salesOrder,
                ignoreIssueId: $goodsIssue->id,
            ),
            'inventoryMap' => $this->availableStockMap($goodsIssue->salesOrder, $goodsIssue->id),
        ]);
    }

    public function update(UpdateGoodsIssueRequest $request, GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->issues->update($goodsIssue, $request->toData());

        return redirect()
            ->route('goods-issues.show', $goodsIssue->id)
            ->with('success', "Goods issue {$goodsIssue->code} updated successfully.");
    }

    public function destroy(GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->authorize('delete', $goodsIssue);

        $code = $goodsIssue->code;

        $this->issues->delete($goodsIssue);

        return redirect()
            ->route('goods-issues.index')
            ->with('success', "Goods issue {$code} deleted successfully.");
    }

    // ── Status actions ───────────────────────────────────────────────────────

    public function complete(GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->authorize('complete', $goodsIssue);

        $this->issues->complete($goodsIssue);

        return redirect()
            ->route('goods-issues.show', $goodsIssue->id)
            ->with('success', "Goods issue {$goodsIssue->code} completed and inventory deducted.");
    }

    public function cancel(GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->authorize('cancel', $goodsIssue);

        $this->issues->cancel($goodsIssue);

        return redirect()
            ->route('goods-issues.show', $goodsIssue->id)
            ->with('success', "Goods issue {$goodsIssue->code} cancelled.");
    }

    public function revert(GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->authorize('revert', $goodsIssue);

        $this->issues->revert($goodsIssue);

        return redirect()
            ->route('goods-issues.show', $goodsIssue->id)
            ->with('success', "Goods issue {$goodsIssue->code} reverted to pending.");
    }

    /**
     * Available stock for the order materials: material id => location id =>
     * quantity that may still be promised.
     *
     * @return array<int, array<int, float>>
     */
    private function availableStockMap(SalesOrder $order, ?int $ignoreIssueId = null): array
    {
        return $this->inventory->availableQuantityMap(
            $order->items->pluck('material_id'),
            $ignoreIssueId,
        );
    }
}
