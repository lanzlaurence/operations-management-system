<?php

namespace App\Livewire\Materials;

use App\Models\Inventory;
use App\Models\Material;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Material detail screen.
 *
 * Alongside the record it answers the two questions the list cannot: where the
 * stock actually sits, and how the maintained list cost and price compare with
 * the averages the movements produced.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Material $material;

    public function mount(Material $material): void
    {
        $this->material = $material;
    }

    /**
     * Stock per location, with the value at the weighted average cost.
     *
     * @return Collection<int, object>
     */
    public function stockByLocation(): Collection
    {
        return Inventory::query()
            ->with('location:id,code,name')
            ->where('material_id', $this->material->id)
            ->orderByDesc('quantity')
            ->get()
            ->map(fn (Inventory $inventory): object => (object) [
                'code' => $inventory->location?->code,
                'name' => $inventory->location?->name,
                'quantity' => Money::quantity($inventory->quantity),
                'value' => Money::round((float) $inventory->quantity * (float) $this->material->avg_unit_cost),
            ]);
    }

    /**
     * Stock totals and where they sit against the maintained levels.
     *
     * @return array<string, float|int|bool|null>
     */
    public function stockSummary(): array
    {
        $onHand = Money::quantity(
            Inventory::query()->where('material_id', $this->material->id)->sum('quantity')
        );

        $reorder = (int) $this->material->reorder_level;
        $max = (int) $this->material->max_stock_level;

        return [
            'on_hand' => $onHand,
            'value' => Money::round($onHand * (float) $this->material->avg_unit_cost),
            'locations' => Inventory::query()
                ->where('material_id', $this->material->id)
                ->where('quantity', '>', 0)
                ->count(),
            'needs_reorder' => $reorder > 0 && $onHand <= $reorder,
            'capacity_percent' => $max > 0 ? min(100, round(($onHand / $max) * 100)) : null,
        ];
    }

    /**
     * List cost/price against the averages the movements produced.
     *
     * @return array<string, float|null>
     */
    public function costing(): array
    {
        $listCost = (float) $this->material->unit_cost;
        $avgCost = (float) $this->material->avg_unit_cost;
        $listPrice = (float) $this->material->unit_price;
        $avgPrice = (float) $this->material->avg_unit_price;

        return [
            'list_cost' => Money::round($listCost),
            'avg_cost' => Money::round($avgCost),
            'cost_variance' => Money::round($avgCost - $listCost),
            'list_price' => Money::round($listPrice),
            'avg_price' => Money::round($avgPrice),
            'price_variance' => Money::round($avgPrice - $listPrice),
            'list_margin' => Money::round($listPrice - $listCost),
            'actual_margin' => Money::round($avgPrice - $avgCost),
        ];
    }

    public function render(): View
    {
        $this->material->load([
            'brand:id,name',
            'category:id,name',
            'uom:id,acronym,description',
            'logs' => fn ($query) => $query->with('user:id,name')->latest('id'),
        ]);

        return view('livewire.materials.show')
            ->title("{$this->material->code} — {$this->material->name}");
    }
}
