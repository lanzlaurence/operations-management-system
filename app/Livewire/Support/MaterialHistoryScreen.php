<?php

namespace App\Livewire\Support;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Inventory;
use App\Models\Material;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Base for the two per-material history screens (purchases and sales).
 *
 * Both show the same thing in different directions: every order line for one
 * material, alongside where that material's stock currently sits. The shared
 * parts - the material binding, the stock panel, paging and sorting - live
 * here, and each screen supplies its own query and columns.
 *
 * These lists paginate in SQL rather than loading a material's entire order
 * history into the browser.
 */
#[Layout('components.layouts.app')]
abstract class MaterialHistoryScreen extends Component
{
    use WithDataTable;

    public Material $material;

    public function mount(Material $material): void
    {
        $this->material = $material;
    }

    /**
     * The order lines for this material.
     *
     * @return Builder<Model>
     */
    abstract protected function historyQuery(): Builder;

    abstract protected function view(): string;

    /** Heading shown on the screen, e.g. "Purchase history". */
    abstract protected function heading(): string;

    protected function defaultSortField(): string
    {
        return 'order_date';
    }

    protected function defaultSortDirection(): string
    {
        return 'desc';
    }

    /**
     * Totals for the lines in view, so the screen answers "how much of this
     * have we traded" without exporting to a spreadsheet.
     *
     * @return array<string, float>
     */
    abstract public function totals(): array;

    /**
     * The history query prepared for aggregation: same filters, but stripped of
     * the row column list and the ordering.
     *
     * Without this the aggregate would inherit `select(items.*, order_date)`
     * and MySQL rejects mixing those with SUM() under `only_full_group_by`.
     *
     * @return Builder<Model>
     */
    protected function aggregateQuery(): Builder
    {
        return $this->applySearch($this->historyQuery())
            ->reorder()
            ->select([]);
    }

    /**
     * Where this material's stock sits right now.
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
            ]);
    }

    public function render(): View
    {
        return view($this->view(), [
            'records' => $this->rows(),
        ])->title("{$this->heading()} — {$this->material->code}");
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    protected function rows(): LengthAwarePaginator
    {
        return $this->applyDataTable($this->historyQuery())->paginate($this->perPage);
    }
}
