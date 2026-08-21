<?php

namespace App\Livewire\PurchaseOrders;

use App\Enums\DiscountType;
use App\Enums\VatType;
use App\Livewire\Forms\PurchaseOrderForm;
use App\Models\Charge;
use App\Models\Material;
use App\Models\Preference;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\PurchaseOrderService;
use App\Services\Support\DocumentTotals;
use App\Services\Support\DocumentTotalsCalculator;
use App\Services\Support\LineCalculator;
use App\Services\Support\LineTotals;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Create and edit screen for a purchase order.
 *
 * Line totals and the document totals recompute on the server as the buyer
 * types, using the same calculators the service saves with - so the figure on
 * screen and the figure stored can never disagree. That is the reason this is
 * not done in JavaScript.
 *
 * Editing is only reachable while the order is a draft; the service enforces it
 * again on save.
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    public PurchaseOrderForm $form;

    public ?PurchaseOrder $purchaseOrder = null;

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        if (! $purchaseOrder?->exists) {
            $this->form->mountForm();

            return;
        }

        if (! $purchaseOrder->canBeEdited()) {
            session()->flash('error', 'Only draft purchase orders can be edited.');

            $this->redirectRoute('purchase-orders.show', $purchaseOrder, navigate: true);

            return;
        }

        $this->purchaseOrder = $purchaseOrder;
        $this->form->setOrder($purchaseOrder->load(['items.material', 'charges']));
    }

    public function isEditing(): bool
    {
        return $this->purchaseOrder !== null;
    }

    // ── Rows ─────────────────────────────────────────────────────────────────

    public function addItem(): void
    {
        $this->form->addItem();
    }

    public function removeItem(int $index): void
    {
        $this->form->removeItem($index);
        $this->resetValidation();
    }

    public function addCharge(): void
    {
        $this->form->addCharge();
    }

    public function removeCharge(int $index): void
    {
        $this->form->removeCharge($index);
    }

    /**
     * Picking a material pre-fills its list cost, so the buyer only types the
     * figure when it differs from what is on file.
     */
    public function updated(string $property): void
    {
        if (preg_match('/^form\.items\.(\d+)\.material_id$/', $property, $matches) !== 1) {
            return;
        }

        $index = (int) $matches[1];
        $materialId = $this->form->items[$index]['material_id'] ?? '';

        $this->form->applyMaterialDefaults(
            $index,
            $materialId === '' ? null : Material::find($materialId),
        );
    }

    // ── Saving ───────────────────────────────────────────────────────────────

    public function save(): void
    {
        $order = $this->form->save(app(PurchaseOrderService::class));

        session()->flash('success', $this->isEditing()
            ? "Purchase order {$order->code} updated."
            : "Purchase order {$order->code} created as a draft.");

        $this->redirectRoute('purchase-orders.show', $order, navigate: true);
    }

    /**
     * Save and post in one step, for an order that is already agreed.
     */
    public function saveAndPost(): void
    {
        $orders = app(PurchaseOrderService::class);

        $order = $this->form->save($orders);
        $orders->post($order);

        session()->flash('success', "Purchase order {$order->code} created and posted.");

        $this->redirectRoute('purchase-orders.show', $order, navigate: true);
    }

    // ── Live figures for the view ────────────────────────────────────────────

    /**
     * Computed money columns per row index.
     *
     * @return array<int, LineTotals|null>
     */
    public function lineTotals(): array
    {
        $calculator = app(LineCalculator::class);

        $totals = [];

        foreach (array_keys($this->form->items) as $index) {
            $totals[$index] = $this->form->lineTotals($index, $calculator);
        }

        return $totals;
    }

    public function documentTotals(): DocumentTotals
    {
        return $this->form->totals(
            app(LineCalculator::class),
            app(DocumentTotalsCalculator::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'code', 'name', 'payment_terms', 'credit_amount']),
            'materials' => Material::query()->active()->with('uom:id,acronym')->orderBy('code')->get(['id', 'code', 'name', 'unit_cost', 'uom_id']),
            'charges' => Charge::query()->active()->orderBy('name')->get(),
            'discountTypes' => DiscountType::options(),
            'vatTypes' => VatType::options(),
            'currency' => Preference::get('currency', 'PHP'),
        ];
    }

    /**
     * The chosen vendor, for the terms shown beside the selector.
     */
    public function selectedVendor(): ?Vendor
    {
        return $this->form->vendor_id === '' ? null : Vendor::find($this->form->vendor_id);
    }

    /**
     * Materials keyed by id, so rows can show the unit without another query.
     *
     * @return Collection<int, Material>
     */
    public function materialsById(): Collection
    {
        return Material::query()
            ->with('uom:id,acronym')
            ->whereKey(collect($this->form->items)->pluck('material_id')->filter())
            ->get()
            ->keyBy('id');
    }

    public function render(): View
    {
        return view('livewire.purchase-orders.form')
            ->title($this->isEditing() ? "Edit {$this->purchaseOrder->code}" : 'Create Purchase Order');
    }
}
