<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Http\Requests\UpdateGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP entry points for goods receipts.
 *
 * Stock only moves in GoodsReceiptService::complete()/cancel(); this class
 * authorizes, shapes the payload for the screens and redirects.
 */
class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $receipts,
        private readonly PurchaseOrderService $orders,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        return Inertia::render('purchasing/goods-receipt/index', [
            'goodsReceipts' => GoodsReceipt::query()
                ->with(['purchaseOrder.vendor', 'location:id,code,name', 'user:id,name'])
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Receiving form for one purchase order, with the outstanding quantity
     * per line already resolved (pending receipts reserved).
     */
    public function create(PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        $this->authorize('create', GoodsReceipt::class);

        if (! $purchaseOrder->canCreateGr()) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'Goods receipt cannot be created for this purchase order.');
        }

        $purchaseOrder->load([
            'vendor',
            'items.material.brand',
            'items.material.category',
            'items.material.uom',
        ]);

        $outstanding = $this->orders->outstandingQuantities($purchaseOrder);

        return Inertia::render('purchasing/goods-receipt/create', [
            'purchaseOrder' => [
                ...$purchaseOrder->toArray(),
                'items' => $purchaseOrder->items
                    ->map(fn (PurchaseOrderItem $item): array => [
                        ...$item->toArray(),
                        'qty_remaining' => $outstanding[$item->id] ?? 0,
                    ])
                    ->all(),
            ],
            'locations' => Location::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request): RedirectResponse
    {
        $data = $request->toData();
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

        $receipt = $this->receipts->create($order, $data);

        return redirect()
            ->route('goods-receipts.show', $receipt->id)
            ->with('success', "Goods receipt {$receipt->code} created successfully.");
    }

    public function show(GoodsReceipt $goodsReceipt): Response
    {
        $this->authorize('view', $goodsReceipt);

        $goodsReceipt->load([
            'purchaseOrder.vendor',
            'location',
            'user:id,name',
            'items.material.uom',
            'items.purchaseOrderItem',
            'logs.user:id,name',
        ]);

        return Inertia::render('purchasing/goods-receipt/show', [
            'goodsReceipt' => $goodsReceipt,
            'can' => $goodsReceipt->actionFlags(),
        ]);
    }

    public function edit(GoodsReceipt $goodsReceipt): Response|RedirectResponse
    {
        $this->authorize('update', $goodsReceipt);

        if (! $goodsReceipt->canBeEdited()) {
            return redirect()
                ->route('goods-receipts.show', $goodsReceipt->id)
                ->with('error', 'Only pending goods receipts can be edited.');
        }

        $goodsReceipt->load([
            'purchaseOrder.vendor',
            'purchaseOrder.items.material',
            'location',
            'items.material',
            'items.purchaseOrderItem',
        ]);

        return Inertia::render('purchasing/goods-receipt/edit', [
            'goodsReceipt' => $goodsReceipt,
            'locations' => Location::query()->orderBy('name')->get(['id', 'code', 'name']),
            'outstanding' => $this->orders->outstandingQuantities(
                $goodsReceipt->purchaseOrder,
                ignoreReceiptId: $goodsReceipt->id,
            ),
        ]);
    }

    public function update(UpdateGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->receipts->update($goodsReceipt, $request->toData());

        return redirect()
            ->route('goods-receipts.show', $goodsReceipt->id)
            ->with('success', "Goods receipt {$goodsReceipt->code} updated successfully.");
    }

    public function destroy(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('delete', $goodsReceipt);

        $code = $goodsReceipt->code;

        $this->receipts->delete($goodsReceipt);

        return redirect()
            ->route('goods-receipts.index')
            ->with('success', "Goods receipt {$code} deleted successfully.");
    }

    // ── Status actions ───────────────────────────────────────────────────────

    public function complete(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('complete', $goodsReceipt);

        $this->receipts->complete($goodsReceipt);

        return redirect()
            ->route('goods-receipts.show', $goodsReceipt->id)
            ->with('success', "Goods receipt {$goodsReceipt->code} completed and inventory updated.");
    }

    public function cancel(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('cancel', $goodsReceipt);

        $this->receipts->cancel($goodsReceipt);

        return redirect()
            ->route('goods-receipts.show', $goodsReceipt->id)
            ->with('success', "Goods receipt {$goodsReceipt->code} cancelled.");
    }

    public function revert(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('revert', $goodsReceipt);

        $this->receipts->revert($goodsReceipt);

        return redirect()
            ->route('goods-receipts.show', $goodsReceipt->id)
            ->with('success', "Goods receipt {$goodsReceipt->code} reverted to pending.");
    }
}
