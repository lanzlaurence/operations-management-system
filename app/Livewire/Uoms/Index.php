<?php

namespace App\Livewire\Uoms;

use App\Livewire\Support\MasterIndex;
use App\Models\Material;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Model;

/**
 * Unit-of-measurement list.
 */
class Index extends MasterIndex
{
    protected function model(): string
    {
        return Uom::class;
    }

    protected function permissionPrefix(): string
    {
        return 'uom';
    }

    protected function label(): string
    {
        return 'Unit of Measurement';
    }

    protected function title(): string
    {
        return 'Unit of Measurement';
    }

    protected function view(): string
    {
        return 'livewire.uoms.index';
    }

    protected function displayColumn(): string
    {
        return 'acronym';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['acronym', 'description'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['acronym', 'created_at', 'updated_at'];
    }

    protected function defaultSortField(): string
    {
        return 'acronym';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * Materials are measured in these, so a unit in use stays put.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        $materials = Material::query()->where('uom_id', $record->getKey())->count();

        return $materials === 0
            ? null
            : "{$record->acronym} is used by {$materials} material(s) and cannot be deleted.";
    }
}
