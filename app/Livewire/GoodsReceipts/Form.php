<?php

namespace App\Livewire\GoodsReceipts;

use App\Livewire\Forms\GoodsReceiptForm;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Preference;
use App\Models\PurchaseOrder;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Receiving screen: raise a receipt against a purchase order, or correct a
 * pending one.
 *
 * Reached two ways - from an order (`purchase-orders.goods-receipts.create`) or
 * by editing an existing pending receipt. Either way the order supplies the
 * lines and the outstanding quantities; nothing here decides what may be
 * received, the service does.
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    public GoodsReceiptForm $form;

    public ?GoodsReceipt $goodsReceipt = null;

    public PurchaseOrder $purchaseOrder;

    /**
     * Creating: the order comes from the route. Editing: from the receipt.
     */
    public function mount(?PurchaseOrder $purchaseOrder = null, ?GoodsReceipt $goodsReceipt = null): void
    {
        $orders = app(PurchaseOrderService::class);

        if ($goodsReceipt?->exists) {
            if (! $goodsReceipt->canBeEdited()) {
                session()->flash('error', 'Only pending goods receipts can be edited.');

                $this->redirectRoute('goods-receipts.show', $goodsReceipt, navigate: true);

                return;
            }

            $goodsReceipt->load(['purchaseOrder.items.material.uom', 'items']);

            $this->goodsReceipt = $goodsReceipt;
            $this->purchaseOrder = $goodsReceipt->purchaseOrder;
            $this->form->setReceipt($goodsReceipt, $orders);

            return;
        }

        if (! $purchaseOrder->canCreateGr()) {
            session()->flash('error', 'Goods receipt cannot be created for this purchase order.');

            $this->redirectRoute('purchase-orders.show', $purchaseOrder, navigate: true);

            return;
        }

        $purchaseOrder->load(['vendor', 'items.material.uom']);

        $this->purchaseOrder = $purchaseOrder;
        $this->form->mountFor($purchaseOrder, $orders);
    }

    public function isEditing(): bool
    {
        return $this->goodsReceipt !== null;
    }

    public function receiveAll(): void
    {
        $this->form->receiveAll();
        $this->resetValidation();
    }

    public function receiveNone(): void
    {
        $this->form->receiveNone();
        $this->resetValidation();
    }

    public function save(): void
    {
        $receipt = $this->form->save(app(GoodsReceiptService::class));

        session()->flash('success', $this->isEditing()
            ? "Goods receipt {$receipt->code} updated."
            : "Goods receipt {$receipt->code} prepared. Complete it to book the stock in.");

        $this->redirectRoute('goods-receipts.show', $receipt, navigate: true);
    }

    /**
     * Save and complete in one step, for stock checked in at the gate.
     */
    public function saveAndComplete(): void
    {
        $receipts = app(GoodsReceiptService::class);

        $receipt = $this->form->save($receipts);
        $receipts->complete($receipt);

        session()->flash('success', "Goods receipt {$receipt->code} completed and stock booked in.");

        $this->redirectRoute('goods-receipts.show', $receipt, navigate: true);
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
        return view('livewire.goods-receipts.form', [
            'currency' => Preference::get('currency', 'PHP'),
            'summary' => $this->form->summary(),
        ])->title($this->isEditing()
            ? "Edit {$this->goodsReceipt->code}"
            : "Receive against {$this->purchaseOrder->code}");
    }
}
