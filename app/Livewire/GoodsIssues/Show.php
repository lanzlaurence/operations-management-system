<?php

namespace App\Livewire\GoodsIssues;

use App\Models\GoodsIssue;
use App\Models\InventoryLog;
use App\Models\Preference;
use App\Services\GoodsIssueService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The goods issue document.
 *
 * Completing is the moment stock exists, so this screen shows what will move
 * before it happens and the movements it produced afterwards - the same
 * inventory log rows the ledger screen shows, filtered to this document.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public GoodsIssue $goodsIssue;

    /** The action awaiting confirmation. */
    public string $confirming = '';

    public function mount(GoodsIssue $goodsIssue): void
    {
        $this->goodsIssue = $goodsIssue;
    }

    public function confirm(string $action): void
    {
        $this->confirming = $action;

        $this->dispatch('open-modal', name: 'confirm-action');
    }

    public function complete(): void
    {
        $this->authorize('complete', $this->goodsIssue);

        app(GoodsIssueService::class)->complete($this->goodsIssue);

        $this->finish("Goods issue {$this->goodsIssue->code} completed and stock booked in.");
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->goodsIssue);

        app(GoodsIssueService::class)->cancel($this->goodsIssue);

        $this->finish("Goods issue {$this->goodsIssue->code} cancelled.");
    }

    public function revert(): void
    {
        $this->authorize('revert', $this->goodsIssue);

        app(GoodsIssueService::class)->revert($this->goodsIssue);

        $this->finish("Goods issue {$this->goodsIssue->code} reverted to pending.");
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->goodsIssue);

        $code = $this->goodsIssue->code;
        $order = $this->goodsIssue->sales_order_id;

        app(GoodsIssueService::class)->delete($this->goodsIssue);

        session()->flash('success', "Goods issue {$code} deleted.");

        $this->redirectRoute('sales-orders.show', $order, navigate: true);
    }

    /**
     * @return array<string, bool>
     */
    public function actions(): array
    {
        return $this->goodsIssue->actionFlags();
    }

    /**
     * @return array<string, float|int>
     */
    public function summary(): array
    {
        $items = $this->goodsIssue->items;

        return [
            'lines' => $items->count(),
            'quantity' => Money::quantity($items->sum('qty_to_ship')),
            'value' => Money::round($items->sum(fn ($item): float => $item->lineValue())),
        ];
    }

    /**
     * The stock movements this issue produced, if any.
     *
     * @return Collection<int, InventoryLog>
     */
    public function movements(): Collection
    {
        return InventoryLog::query()
            ->with(['material:id,code,name', 'inventory:id,code', 'user:id,name'])
            ->where('reference_type', GoodsIssue::class)
            ->where('reference_id', $this->goodsIssue->id)
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        $this->goodsIssue->load([
            'salesOrder.customer',
            'location',
            'user:id,name',
            'items.material:id,code,name,uom_id',
            'items.material.uom:id,acronym',
            'items.salesOrderItem:id,qty_ordered,qty_shipped',
            'logs' => fn ($query) => $query->with('user:id,name')->latest('id'),
        ]);

        return view('livewire.goods-issues.show', [
            'currency' => Preference::get('currency', 'PHP'),
        ])->title("{$this->goodsIssue->code} — {$this->goodsIssue->salesOrder?->code}");
    }

    private function finish(string $message): void
    {
        $this->confirming = '';

        $this->dispatch('close-modal', name: 'confirm-action');
        $this->dispatch('toast', type: 'success', message: $message);

        $this->goodsIssue->refresh();
    }
}
