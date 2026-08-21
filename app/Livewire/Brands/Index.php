<?php

namespace App\Livewire\Brands;

use App\Livewire\Support\MasterIndex;
use App\Models\Brand;
use App\Models\Material;
use Illuminate\Database\Eloquent\Model;

/**
 * Brand list.
 */
class Index extends MasterIndex
{
    protected function model(): string
    {
        return Brand::class;
    }

    protected function permissionPrefix(): string
    {
        return 'brand';
    }

    protected function label(): string
    {
        return 'Brand';
    }

    protected function view(): string
    {
        return 'livewire.brands.index';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'description'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'created_at', 'updated_at'];
    }

    protected function defaultSortField(): string
    {
        return 'name';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * A brand still attached to materials stays put, otherwise those materials
     * would point at a record no one can see any more.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        $materials = Material::query()->where('brand_id', $record->getKey())->count();

        return $materials === 0
            ? null
            : "{$record->name} is used by {$materials} material(s) and cannot be deleted.";
    }
}
