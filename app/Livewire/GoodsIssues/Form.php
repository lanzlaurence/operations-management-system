<?php

namespace App\Livewire\GoodsIssues;

use App\Livewire\Forms\GoodsIssueForm;
use App\Models\GoodsIssue;
use App\Models\Location;
use App\Models\Preference;
use App\Models\SalesOrder;
use App\Services\GoodsIssueService;
use App\Services\InventoryService;
use App\Services\SalesOrderService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Shipping screen: raise a issue against a sales order, or correct a
 * pending one.
 *
 * Reached two ways - from an order (`sales-orders.goods-issues.create`) or
 * by editing an existing pending issue. Either way the order supplies the
 * lines and the outstanding quantities; nothing here decides what may be
 * shipped, the service does.
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    public GoodsIssueForm $form;

    public ?GoodsIssue $goodsIssue = null;

    public SalesOrder $salesOrder;

    /**
     * Creating: the order comes from the route. Editing: from the issue.
     */
    public function mount(?SalesOrder $salesOrder = null, ?GoodsIssue $goodsIssue = null): void
    {
        $orders = app(SalesOrderService::class);

        if ($goodsIssue?->exists) {
            if (! $goodsIssue->canBeEdited()) {
                session()->flash('error', 'Only pending goods issues can be edited.');

                $this->redirectRoute('goods-issues.show', $goodsIssue, navigate: true);

                return;
            }

            $goodsIssue->load(['salesOrder.items.material.uom', 'items']);

            $this->goodsIssue = $goodsIssue;
            $this->salesOrder = $goodsIssue->salesOrder;
            $this->form->setIssue($goodsIssue, $orders);
            $this->form->refreshAvailability(app(InventoryService::class));

            return;
        }

        if (! $salesOrder->canCreateGi()) {
            session()->flash('error', 'Goods issue cannot be created for this sales order.');

            $this->redirectRoute('sales-orders.show', $salesOrder, navigate: true);

            return;
        }

        $salesOrder->load(['customer', 'items.material.uom']);

        $this->salesOrder = $salesOrder;
        $this->form->mountFor($salesOrder, $orders);
    }

    public function isEditing(): bool
    {
        return $this->goodsIssue !== null;
    }

    /**
     * Choosing a location decides what can actually be shipped, so the limits
     * are re-resolved whenever it changes.
     */
    public function updatedFormLocationId(): void
    {
        $this->form->refreshAvailability(app(InventoryService::class));
        $this->resetValidation();
    }

    public function shipAll(): void
    {
        $this->form->shipAll();
        $this->resetValidation();
    }

    public function shipNone(): void
    {
        $this->form->shipNone();
        $this->resetValidation();
    }

    public function save(): void
    {
        $issue = $this->form->save(app(GoodsIssueService::class));

        session()->flash('success', $this->isEditing()
            ? "Goods issue {$issue->code} updated."
            : "Goods issue {$issue->code} prepared. Complete it to book the stock in.");

        $this->redirectRoute('goods-issues.show', $issue, navigate: true);
    }

    /**
     * Save and complete in one step, for stock checked in at the gate.
     */
    public function saveAndComplete(): void
    {
        $issues = app(GoodsIssueService::class);

        $issue = $this->form->save($issues);
        $issues->complete($issue);

        session()->flash('success', "Goods issue {$issue->code} completed and stock booked in.");

        $this->redirectRoute('goods-issues.show', $issue, navigate: true);
    }

    /**
     * @return Collection<int, Location>
     */
    public function locations(): Collection
    {
        return Location::query()->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function render(): View
    {
        return view('livewire.goods-issues.form', [
            'currency' => Preference::get('currency', 'PHP'),
            'summary' => $this->form->summary(),
        ])->title($this->isEditing()
            ? "Edit {$this->goodsIssue->code}"
            : "Ship against {$this->salesOrder->code}");
    }
}
