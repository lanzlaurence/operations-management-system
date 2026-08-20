<?php

namespace App\Services;

use App\Enums\GoodsIssueStatus;
use App\Enums\GoodsReceiptStatus;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptItem;
use App\Models\Material;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Keeps `materials.avg_unit_cost` and `materials.avg_unit_price` in step with
 * what actually moved through the warehouse.
 *
 *  - average cost  = weighted average of completed goods receipt lines
 *  - average price = weighted average of completed goods issue lines
 *
 * Both are recomputed from scratch (rather than incrementally) whenever a
 * receipt or issue is completed, cancelled or reverted, so the figure can
 * never drift away from the movement history. When no movement is left the
 * average falls back to the material's own unit cost/price instead of keeping
 * the stale number the previous movements had produced.
 */
class MaterialCostingService
{
    /**
     * Recompute both averages for one material.
     */
    public function sync(Material|int $material): void
    {
        $material = $this->resolve($material);

        if ($material === null) {
            return;
        }

        $this->syncAverageCost($material);
        $this->syncAveragePrice($material);
    }

    /**
     * Recompute both averages for a list of material ids.
     *
     * @param  iterable<int|Material>  $materials
     */
    public function syncMany(iterable $materials): void
    {
        foreach ($this->uniqueIds($materials) as $id) {
            $this->sync($id);
        }
    }

    /**
     * Weighted average purchase cost across completed goods receipts.
     */
    public function syncAverageCost(Material|int $material): void
    {
        $material = $this->resolve($material);

        if ($material === null) {
            return;
        }

        $totals = GoodsReceiptItem::query()
            ->where('material_id', $material->id)
            ->whereHas('goodsReceipt', fn (Builder $query) => $query->where('status', GoodsReceiptStatus::Completed->value))
            ->selectRaw('COALESCE(SUM(qty_to_receive), 0) as qty')
            ->selectRaw('COALESCE(SUM(qty_to_receive * unit_cost), 0) as value')
            ->first();

        $quantity = (float) ($totals->qty ?? 0);
        $value = (float) ($totals->value ?? 0);

        $this->write($material, 'avg_unit_cost', $quantity > 0
            ? Money::round($value / $quantity)
            : Money::round($material->unit_cost));
    }

    /**
     * Weighted average selling price across completed goods issues.
     */
    public function syncAveragePrice(Material|int $material): void
    {
        $material = $this->resolve($material);

        if ($material === null) {
            return;
        }

        $totals = GoodsIssueItem::query()
            ->where('material_id', $material->id)
            ->whereHas('goodsIssue', fn (Builder $query) => $query->where('status', GoodsIssueStatus::Completed->value))
            ->selectRaw('COALESCE(SUM(qty_to_ship), 0) as qty')
            ->selectRaw('COALESCE(SUM(qty_to_ship * unit_price), 0) as value')
            ->first();

        $quantity = (float) ($totals->qty ?? 0);
        $value = (float) ($totals->value ?? 0);

        $this->write($material, 'avg_unit_price', $quantity > 0
            ? Money::round($value / $quantity)
            : Money::round($material->unit_price));
    }

    /**
     * Write the column without touching timestamps or firing model events -
     * this is a derived figure, not a user edit, and it must not show up as a
     * material update in the audit trail.
     */
    private function write(Material $material, string $column, float $value): void
    {
        if (Money::equals($material->{$column}, $value)) {
            return;
        }

        DB::table($material->getTable())
            ->where('id', $material->id)
            ->update([$column => $value]);

        $material->setAttribute($column, $value)->syncOriginalAttribute($column);
    }

    private function resolve(Material|int $material): ?Material
    {
        return $material instanceof Material ? $material : Material::find($material);
    }

    /**
     * @param  iterable<int|Material>  $materials
     * @return array<int, int>
     */
    private function uniqueIds(iterable $materials): array
    {
        $ids = [];

        foreach ($materials as $material) {
            $id = $material instanceof Material ? $material->id : (int) $material;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
