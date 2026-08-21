<?php

namespace App\Livewire\PurchaseOrders;

use App\Models\Preference;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use App\Support\Money;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The purchase order document: header, lines, charges, receipts and its trail.
 *
 * The status actions live here rather than on the list, because each one has
 * consequences worth seeing first - cancelling takes the receipts with it and
 * reverses their stock. Every action delegates to PurchaseOrderService, which
 * owns the rules and throws a BusinessRuleException that renders itself as a
 * message when one is broken.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public PurchaseOrder $purchaseOrder;

    /** The action awaiting confirmation, so the modal can name it. */
    public string $confirming = '';

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->purchaseOrder = $purchaseOrder;
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

        app(PurchaseOrderService::class)->post($this->purchaseOrder);

        $this->finish("Purchase order {$this->purchaseOrder->code} posted.");
    }

    public function cancel(): void
    {
        $this->authorizeAction('cancel');

        app(PurchaseOrderService::class)->cancel($this->purchaseOrder);

        $this->finish("Purchase order {$this->purchaseOrder->code} cancelled, including its goods receipts.");
    }

    public function revert(): void
    {
        $this->authorizeAction('revert');

        app(PurchaseOrderService::class)->revert($this->purchaseOrder);

        $this->finish("Purchase order {$this->purchaseOrder->code} reverted to draft.");
    }

    public function delete(): void
    {
        $this->authorizeAction('delete');

        $code = $this->purchaseOrder->code;

        app(PurchaseOrderService::class)->delete($this->purchaseOrder);

        session()->flash('success', "Purchase order {$code} deleted.");

        $this->redirectRoute('purchase-orders.index', navigate: true);
    }

    // ── Reading ──────────────────────────────────────────────────────────────

    /**
     * What may be done to this order right now, from the model's own rules.
     *
     * @return array<string, bool>
     */
    public function actions(): array
    {
        return $this->purchaseOrder->actionFlags();
    }

    /**
     * Outstanding quantity per line, pending receipts reserved, which is what
     * decides whether another receipt can be raised.
     *
     * @return array<int, float>
     */
    public function outstanding(): array
    {
        return app(PurchaseOrderService::class)->outstandingQuantities($this->purchaseOrder);
    }

    /**
     * Received against ordered, as a percentage, for the progress bar.
     */
    public function receivedPercent(): int
    {
        $ordered = (float) $this->purchaseOrder->items->sum('qty_ordered');
        $received = (float) $this->purchaseOrder->items->sum('qty_received');

        return $ordered > 0 ? (int) min(100, round(($received / $ordered) * 100)) : 0;
    }

    /**
     * @return array<string, float>
     */
    public function quantities(): array
    {
        return [
            'ordered' => Money::quantity($this->purchaseOrder->items->sum('qty_ordered')),
            'received' => Money::quantity($this->purchaseOrder->items->sum('qty_received')),
        ];
    }

    public function render(): View
    {
        $this->purchaseOrder->load([
            'vendor',
            'user:id,name',
            'items.material:id,code,name,uom_id',
            'items.material.uom:id,acronym',
            'charges',
            'goodsReceipts' => fn ($query) => $query->with(['location:id,code,name', 'user:id,name'])->latest('id'),
            'logs' => fn ($query) => $query->with('user:id,name')->latest('id'),
        ]);

        return view('livewire.purchase-orders.show', [
            'currency' => Preference::get('currency', 'PHP'),
        ])->title("{$this->purchaseOrder->code} — {$this->purchaseOrder->vendor?->name}");
    }

    /**
     * Guard the action, then close the confirmation.
     */
    private function authorizeAction(string $ability): void
    {
        $this->authorize($ability, $this->purchaseOrder);
    }

    private function finish(string $message): void
    {
        $this->confirming = '';

        $this->dispatch('close-modal', name: 'confirm-action');
        $this->dispatch('toast', type: 'success', message: $message);

        $this->purchaseOrder->refresh();
    }
}
