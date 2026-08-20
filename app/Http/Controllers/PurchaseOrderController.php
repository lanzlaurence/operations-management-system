<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Charge;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP entry points for purchase orders.
 *
 * Every method follows the same shape: authorize through PurchaseOrderPolicy,
 * hand the validated data object to PurchaseOrderService, then redirect. All
 * business rules and all writes live in the service, so the flow behaves
 * identically whether it is driven by the UI, a seeder or a test.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $orders) {}

    /**
     * Paginated-free list for the index table (the frontend filters locally).
     */
    public function index(): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        return Inertia::render('purchasing/purchase-order/index', [
            'purchaseOrders' => PurchaseOrder::query()
                ->with(['vendor', 'user:id,name'])
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        return Inertia::render('purchasing/purchase-order/create', $this->formOptions());
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create($request->toData());

        return redirect()
            ->route('purchase-orders.show', $order->id)
            ->with('success', "Purchase order {$order->code} created successfully.");
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'vendor',
            'user:id,name',
            'items.material.uom',
            'charges.charge',
            'goodsReceipts.location',
            'goodsReceipts.user:id,name',
            'logs.user:id,name',
        ]);

        return Inertia::render('purchasing/purchase-order/show', [
            'purchaseOrder' => $purchaseOrder,
            'can' => $purchaseOrder->actionFlags(),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        if (! $purchaseOrder->canBeEdited()) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'Only draft purchase orders can be edited.');
        }

        $purchaseOrder->load(['vendor', 'items.material', 'charges']);

        return Inertia::render('purchasing/purchase-order/edit', [
            'purchaseOrder' => $purchaseOrder,
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->orders->update($purchaseOrder, $request->toData());

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder->id)
            ->with('success', "Purchase order {$purchaseOrder->code} updated successfully.");
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);

        $code = $purchaseOrder->code;

        $this->orders->delete($purchaseOrder);

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', "Purchase order {$code} deleted successfully.");
    }

    // ── Status actions ───────────────────────────────────────────────────────

    public function post(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('post', $purchaseOrder);

        $this->orders->post($purchaseOrder);

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder->id)
            ->with('success', "Purchase order {$purchaseOrder->code} posted successfully.");
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $this->orders->cancel($purchaseOrder);

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder->id)
            ->with('success', "Purchase order {$purchaseOrder->code} cancelled, including its goods receipts.");
    }

    public function revert(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('revert', $purchaseOrder);

        $this->orders->revert($purchaseOrder);

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder->id)
            ->with('success', "Purchase order {$purchaseOrder->code} reverted to draft.");
    }

    /**
     * Master data the create and edit forms need.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'vendors' => Vendor::query()->active()->orderBy('name')->get(),
            'materials' => Material::query()->active()->withMasterData()->orderBy('name')->get(),
            'charges' => Charge::query()->active()->orderBy('name')->get(),
        ];
    }
}
