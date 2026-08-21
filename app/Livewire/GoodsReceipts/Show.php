<?php

namespace App\Livewire\GoodsReceipts;

use App\Models\GoodsReceipt;
use App\Models\InventoryLog;
use App\Models\Preference;
use App\Services\GoodsReceiptService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The goods receipt document.
 *
 * Completing is the moment stock exists, so this screen shows what will move
 * before it happens and the movements it produced afterwards - the same
 * inventory log rows the ledger screen shows, filtered to this document.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public GoodsReceipt $goodsReceipt;

    /** The action awaiting confirmation. */
    public string $confirming = '';

    public function mount(GoodsReceipt $goodsReceipt): void
    {
        $this->goodsReceipt = $goodsReceipt;
    }

    public function confirm(string $action): void
    {
        $this->confirming = $action;

        $this->dispatch('open-modal', name: 'confirm-action');
    }

    public function complete(): void
    {
        $this->authorize('complete', $this->goodsReceipt);

        app(GoodsReceiptService::class)->complete($this->goodsReceipt);

        $this->finish("Goods receipt {$this->goodsReceipt->code} completed and stock booked in.");
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->goodsReceipt);

        app(GoodsReceiptService::class)->cancel($this->goodsReceipt);

        $this->finish("Goods receipt {$this->goodsReceipt->code} cancelled.");
    }

    public function revert(): void
    {
        $this->authorize('revert', $this->goodsReceipt);

        app(GoodsReceiptService::class)->revert($this->goodsReceipt);

        $this->finish("Goods receipt {$this->goodsReceipt->code} reverted to pending.");
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->goodsReceipt);

        $code = $this->goodsReceipt->code;
        $order = $this->goodsReceipt->purchase_order_id;

        app(GoodsReceiptService::class)->delete($this->goodsReceipt);

        session()->flash('success', "Goods receipt {$code} deleted.");

        $this->redirectRoute('purchase-orders.show', $order, navigate: true);
    }

    /**
     * @return array<string, bool>
     */
    public function actions(): array
    {
        return $this->goodsReceipt->actionFlags();
    }

    /**
     * @return array<string, float|int>
     */
    public function summary(): array
    {
        $items = $this->goodsReceipt->items;

        return [
            'lines' => $items->count(),
            'quantity' => Money::quantity($items->sum('qty_to_receive')),
            'value' => Money::round($items->sum(fn ($item): float => $item->lineValue())),
        ];
    }

    /**
     * The stock movements this receipt produced, if any.
     *
     * @return Collection<int, InventoryLog>
     */
    public function movements(): Collection
    {
        return InventoryLog::query()
            ->with(['material:id,code,name', 'inventory:id,code', 'user:id,name'])
            ->where('reference_type', GoodsReceipt::class)
            ->where('reference_id', $this->goodsReceipt->id)
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        $this->goodsReceipt->load([
            'purchaseOrder.vendor',
            'location',
            'user:id,name',
            'items.material:id,code,name,uom_id',
            'items.material.uom:id,acronym',
            'items.purchaseOrderItem:id,qty_ordered,qty_received',
            'logs' => fn ($query) => $query->with('user:id,name')->latest('id'),
        ]);

        return view('livewire.goods-receipts.show', [
            'currency' => Preference::get('currency', 'PHP'),
        ])->title("{$this->goodsReceipt->code} — {$this->goodsReceipt->purchaseOrder?->code}");
    }

    private function finish(string $message): void
    {
        $this->confirming = '';

        $this->dispatch('close-modal', name: 'confirm-action');
        $this->dispatch('toast', type: 'success', message: $message);

        $this->goodsReceipt->refresh();
    }
}
