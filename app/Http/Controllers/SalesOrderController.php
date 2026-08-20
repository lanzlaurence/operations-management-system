<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\UpdateSalesOrderRequest;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Material;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP entry points for sales orders.
 *
 * Mirrors PurchaseOrderController: authorize through SalesOrderPolicy, hand
 * the validated data object to SalesOrderService, redirect. No business rules
 * and no writes here.
 */
class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $orders) {}

    public function index(): Response
    {
        $this->authorize('viewAny', SalesOrder::class);

        return Inertia::render('sales/sales-order/index', [
            'salesOrders' => SalesOrder::query()
                ->with(['customer', 'user:id,name'])
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SalesOrder::class);

        return Inertia::render('sales/sales-order/create', $this->formOptions());
    }

    public function store(StoreSalesOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create($request->toData());

        return redirect()
            ->route('sales-orders.show', $order->id)
            ->with('success', "Sales order {$order->code} created successfully.");
    }

    public function show(SalesOrder $salesOrder): Response
    {
        $this->authorize('view', $salesOrder);

        $salesOrder->load([
            'customer',
            'user:id,name',
            'items.material.uom',
            'charges.charge',
            'goodsIssues.location',
            'goodsIssues.user:id,name',
            'logs.user:id,name',
        ]);

        return Inertia::render('sales/sales-order/show', [
            'salesOrder' => $salesOrder,
            'can' => $salesOrder->actionFlags(),
        ]);
    }

    public function edit(SalesOrder $salesOrder): Response|RedirectResponse
    {
        $this->authorize('update', $salesOrder);

        if (! $salesOrder->canBeEdited()) {
            return redirect()
                ->route('sales-orders.show', $salesOrder->id)
                ->with('error', 'Only draft sales orders can be edited.');
        }

        $salesOrder->load(['customer', 'items.material', 'charges']);

        return Inertia::render('sales/sales-order/edit', [
            'salesOrder' => $salesOrder,
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->orders->update($salesOrder, $request->toData());

        return redirect()
            ->route('sales-orders.show', $salesOrder->id)
            ->with('success', "Sales order {$salesOrder->code} updated successfully.");
    }

    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('delete', $salesOrder);

        $code = $salesOrder->code;

        $this->orders->delete($salesOrder);

        return redirect()
            ->route('sales-orders.index')
            ->with('success', "Sales order {$code} deleted successfully.");
    }

    // ── Status actions ───────────────────────────────────────────────────────

    public function post(SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('post', $salesOrder);

        $this->orders->post($salesOrder);

        return redirect()
            ->route('sales-orders.show', $salesOrder->id)
            ->with('success', "Sales order {$salesOrder->code} posted successfully.");
    }

    public function cancel(SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('cancel', $salesOrder);

        $this->orders->cancel($salesOrder);

        return redirect()
            ->route('sales-orders.show', $salesOrder->id)
            ->with('success', "Sales order {$salesOrder->code} cancelled, including its goods issues.");
    }

    public function revert(SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('revert', $salesOrder);

        $this->orders->revert($salesOrder);

        return redirect()
            ->route('sales-orders.show', $salesOrder->id)
            ->with('success', "Sales order {$salesOrder->code} reverted to draft.");
    }

    /**
     * Master data the create and edit forms need.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'materials' => Material::query()->active()->withMasterData()->orderBy('name')->get(),
            'charges' => Charge::query()->active()->orderBy('name')->get(),
        ];
    }
}
