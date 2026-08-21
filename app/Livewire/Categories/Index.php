<?php

namespace App\Livewire\Categories;

use App\Livewire\Support\MasterIndex;
use App\Models\Category;
use App\Models\Material;
use Illuminate\Database\Eloquent\Model;

/**
 * Category list.
 */
class Index extends MasterIndex
{
    protected function model(): string
    {
        return Category::class;
    }

    protected function permissionPrefix(): string
    {
        return 'category';
    }

    protected function label(): string
    {
        return 'Category';
    }

    protected function view(): string
    {
        return 'livewire.categories.index';
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
     * A category still attached to materials stays put, otherwise those materials
     * would point at a record no one can see any more.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        $materials = Material::query()->where('category_id', $record->getKey())->count();

        return $materials === 0
            ? null
            : "{$record->name} is used by {$materials} material(s) and cannot be deleted.";
    }
}
