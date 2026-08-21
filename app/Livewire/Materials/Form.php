<?php

namespace App\Livewire\Materials;

use App\Enums\RecordStatus;
use App\Livewire\Forms\MaterialForm;
use App\Livewire\Support\MasterForm;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Material;
use App\Models\Preference;
use App\Models\Uom;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;

/**
 * Create and edit screen for a material.
 *
 * The form is grouped the way the data is used: identity, classification,
 * pricing, stock levels, dimensions and tracking. Margin is shown live from
 * the list cost and price, because getting that pair wrong is the mistake this
 * screen exists to prevent.
 */
class Form extends MasterForm
{
    public MaterialForm $form;

    public ?Material $material = null;

    public function mount(?Material $material = null): void
    {
        if ($material?->exists) {
            $this->material = $material;
            $this->form->setMaterial($material);
        }
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->material;
    }

    protected function indexRoute(): string
    {
        return 'materials.index';
    }

    protected function label(): string
    {
        return 'Material';
    }

    protected function view(): string
    {
        return 'livewire.materials.form';
    }

    protected function title(): string
    {
        return $this->isEditing()
            ? "Edit {$this->material->code} — {$this->material->name}"
            : 'Create Material';
    }

    /**
     * Option lists and display settings the form needs.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'brands' => Brand::query()->orderBy('name')->pluck('name', 'id'),
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id'),
            'uoms' => Uom::query()->orderBy('acronym')->pluck('acronym', 'id'),
            'statuses' => RecordStatus::options(),
            'currency' => Preference::get('currency', 'PHP'),
        ];
    }

    /**
     * Margin between the list cost and the list price.
     *
     * @return array{amount: float, percent: float|null}
     */
    public function margin(): array
    {
        $cost = (float) $this->form->unit_cost;
        $price = (float) $this->form->unit_price;

        return [
            'amount' => Money::round($price - $cost),
            'percent' => $price > 0 ? round((($price - $cost) / $price) * 100, 1) : null,
        ];
    }
}
