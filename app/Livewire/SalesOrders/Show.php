<?php

namespace App\Livewire\SalesOrders;

use App\Models\Preference;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use App\Support\Money;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The sales order document: header, lines, charges, issues and its trail.
 *
 * The status actions live here rather than on the list, because each one has
 * consequences worth seeing first - cancelling takes the issues with it and
 * reverses their stock. Every action delegates to SalesOrderService, which
 * owns the rules and throws a BusinessRuleException that renders itself as a
 * message when one is broken.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public SalesOrder $salesOrder;

    /** The action awaiting confirmation, so the modal can name it. */
    public string $confirming = '';

    public function mount(SalesOrder $salesOrder): void
    {
        $this->salesOrder = $salesOrder;
    }

    // ── Status actions ───────────────────────────────────────────────────────

    public function confirm(string $action): void
    {
        $this->confirming = $action;

        $this->dispatch('open-modal', name: 'confirm-action');
    }

    public function post(): void
    {
        $this->authorizeAction('post');

        app(SalesOrderService::class)->post($this->salesOrder);

        $this->finish("Sales order {$this->salesOrder->code} posted.");
    }

    public function cancel(): void
    {
        $this->authorizeAction('cancel');

        app(SalesOrderService::class)->cancel($this->salesOrder);

        $this->finish("Sales order {$this->salesOrder->code} cancelled, including its goods issues.");
    }

    public function revert(): void
    {
        $this->authorizeAction('revert');

        app(SalesOrderService::class)->revert($this->salesOrder);

        $this->finish("Sales order {$this->salesOrder->code} reverted to draft.");
    }

    public function delete(): void
    {
        $this->authorizeAction('delete');

        $code = $this->salesOrder->code;

        app(SalesOrderService::class)->delete($this->salesOrder);

        session()->flash('success', "Sales order {$code} deleted.");

        $this->redirectRoute('sales-orders.index', navigate: true);
    }

    // ── Reading ──────────────────────────────────────────────────────────────

    /**
     * What may be done to this order right now, from the model's own rules.
     *
     * @return array<string, bool>
     */
    public function actions(): array
    {
        return $this->salesOrder->actionFlags();
    }

    /**
     * Outstanding quantity per line, pending issues reserved, which is what
     * decides whether another issue can be raised.
     *
     * @return array<int, float>
     */
    public function outstanding(): array
    {
        return app(SalesOrderService::class)->outstandingQuantities($this->salesOrder);
    }

    /**
     * Shipped against ordered, as a percentage, for the progress bar.
     */
    public function shippedPercent(): int
    {
        $ordered = (float) $this->salesOrder->items->sum('qty_ordered');
        $shipped = (float) $this->salesOrder->items->sum('qty_shipped');

        return $ordered > 0 ? (int) min(100, round(($shipped / $ordered) * 100)) : 0;
    }

    /**
     * @return array<string, float>
     */
    public function quantities(): array
    {
        return [
            'ordered' => Money::quantity($this->salesOrder->items->sum('qty_ordered')),
            'shipped' => Money::quantity($this->salesOrder->items->sum('qty_shipped')),
        ];
    }

    public function render(): View
    {
        $this->salesOrder->load([
            'customer',
            'user:id,name',
            'items.material:id,code,name,uom_id',
            'items.material.uom:id,acronym',
            'charges',
            'goodsIssues' => fn ($query) => $query->with(['location:id,code,name', 'user:id,name'])->latest('id'),
            'logs' => fn ($query) => $query->with('user:id,name')->latest('id'),
        ]);

        return view('livewire.sales-orders.show', [
            'currency' => Preference::get('currency', 'PHP'),
        ])->title("{$this->salesOrder->code} — {$this->salesOrder->customer?->name}");
    }

    /**
     * Guard the action, then close the confirmation.
     */
    private function authorizeAction(string $ability): void
    {
        $this->authorize($ability, $this->salesOrder);
    }

    private function finish(string $message): void
    {
        $this->confirming = '';

        $this->dispatch('close-modal', name: 'confirm-action');
        $this->dispatch('toast', type: 'success', message: $message);

        $this->salesOrder->refresh();
    }
}
