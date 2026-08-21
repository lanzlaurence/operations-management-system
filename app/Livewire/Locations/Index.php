<?php

namespace App\Livewire\Locations;

use App\Livewire\Support\MasterIndex;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

/**
 * Location list.
 *
 * Locations are where stock physically sits, so the list also shows how much
 * each one holds - the figure that decides whether it may be deleted.
 */
class Index extends MasterIndex
{
    protected function model(): string
    {
        return Location::class;
    }

    protected function permissionPrefix(): string
    {
        return 'location';
    }

    protected function label(): string
    {
        return 'Location';
    }

    protected function view(): string
    {
        return 'livewire.locations.index';
    }

    protected function displayColumn(): string
    {
        return 'code';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['code', 'name', 'description'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'name', 'created_at', 'updated_at'];
    }

    protected function defaultSortField(): string
    {
        return 'code';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * Stock records per location, keyed by location id, so the table can show
     * the count without a query per row.
     *
     * @return array<int, int>
     */
    public function stockCounts(): array
    {
        return Inventory::query()
            ->selectRaw('location_id, COUNT(*) as total')
            ->groupBy('location_id')
            ->pluck('total', 'location_id')
            ->all();
    }

    /**
     * A location that holds stock, or that any receipt or issue was booked
     * against, is part of the movement history and cannot be removed.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        $id = $record->getKey();

        $withStock = Inventory::query()
            ->where('location_id', $id)
            ->where('quantity', '>', 0)
            ->count();

        if ($withStock > 0) {
            return "{$record->code} still holds stock for {$withStock} material(s) and cannot be deleted.";
        }

        $documents = GoodsReceipt::query()->where('location_id', $id)->count()
            + GoodsIssue::query()->where('location_id', $id)->count();

        return $documents === 0
            ? null
            : "{$record->code} is referenced by {$documents} stock document(s) and cannot be deleted.";
    }
}
