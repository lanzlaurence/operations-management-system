<?php

namespace App\Services;

use App\Enums\GoodsIssueStatus;
use App\Enums\InventoryMovementType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\GoodsIssueItem;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Location;
use App\Models\Material;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Every change to a stock quantity goes through this service.
 *
 * Why it exists: quantities used to be written from four different
 * controllers, each with its own copy of the "find the row, add, log it"
 * dance, and none of them locked. Here the row is always locked for the
 * duration of the movement, the log line is always written from the same
 * before/after pair, and going negative is refused rather than silently
 * clamped to zero.
 */
class InventoryService
{
    /**
     * Book stock into a location, creating (or restoring) the inventory row
     * when the material has never been stored there.
     */
    public function increase(
        int $materialId,
        int $locationId,
        float $quantity,
        InventoryMovementType $type,
        ?Model $reference = null,
        ?string $remarks = null,
        ?int $transferLocationId = null,
    ): InventoryLog {
        $quantity = Money::quantity($quantity);

        $this->guardPositive($quantity);

        return DB::transaction(function () use ($materialId, $locationId, $quantity, $type, $reference, $remarks, $transferLocationId): InventoryLog {
            $inventory = $this->lockOrCreate($materialId, $locationId);

            $before = Money::quantity($inventory->quantity);
            $after = Money::quantity($before + $quantity);

            $inventory->update(['quantity' => $after]);

            return $this->writeLog($inventory, $type, $before, $quantity, $after, $reference, $remarks, $transferLocationId);
        });
    }

    /**
     * Take stock out of a location.
     *
     * @throws InsufficientStockException when the location does not hold enough
     */
    public function decrease(
        int $materialId,
        int $locationId,
        float $quantity,
        InventoryMovementType $type,
        ?Model $reference = null,
        ?string $remarks = null,
        ?int $transferLocationId = null,
    ): InventoryLog {
        $quantity = Money::quantity($quantity);

        $this->guardPositive($quantity);

        return DB::transaction(function () use ($materialId, $locationId, $quantity, $type, $reference, $remarks, $transferLocationId): InventoryLog {
            $inventory = $this->lockExisting($materialId, $locationId);

            $before = Money::quantity($inventory->quantity);

            if ($before + 0.0000005 < $quantity) {
                throw InsufficientStockException::for(
                    $inventory->material ?? Material::findOrFail($materialId),
                    $inventory->location ?? Location::find($locationId),
                    $before,
                    $quantity,
                );
            }

            $after = Money::quantity($before - $quantity);

            $inventory->update(['quantity' => $after]);

            return $this->writeLog($inventory, $type, $before, -$quantity, $after, $reference, $remarks, $transferLocationId);
        });
    }

    /**
     * Open a stock record with its first quantity (manual "initial stock").
     */
    public function initialise(int $materialId, int $locationId, float $quantity, ?string $remarks = null): Inventory
    {
        return DB::transaction(function () use ($materialId, $locationId, $quantity, $remarks): Inventory {
            $existing = Inventory::query()
                ->where('material_id', $materialId)
                ->where('location_id', $locationId)
                ->exists();

            if ($existing) {
                throw BusinessRuleException::make('This material already exists in the selected location.');
            }

            $log = $this->increase(
                materialId: $materialId,
                locationId: $locationId,
                quantity: $quantity,
                type: InventoryMovementType::Initial,
                remarks: $remarks,
            );

            return $log->inventory;
        });
    }

    /**
     * Set an absolute quantity on an existing record (manual adjustment). The
     * log keeps the signed difference so the movement history still adds up.
     */
    public function adjustTo(Inventory $inventory, float $quantity, ?string $remarks = null): InventoryLog
    {
        $quantity = Money::quantity($quantity);

        if ($quantity < 0) {
            throw BusinessRuleException::make('Adjusted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($inventory, $quantity, $remarks): InventoryLog {
            $locked = $this->lockExisting($inventory->material_id, $inventory->location_id);

            $before = Money::quantity($locked->quantity);

            if (Money::quantityEquals($before, $quantity)) {
                throw BusinessRuleException::make('No changes detected. Please enter a different quantity.');
            }

            $locked->update(['quantity' => $quantity]);

            return $this->writeLog(
                $locked,
                InventoryMovementType::Adjustment,
                $before,
                $quantity - $before,
                $quantity,
                null,
                $remarks,
            );
        });
    }

    /**
     * Move stock between two locations as one atomic out/in pair.
     *
     * @return array{0: InventoryLog, 1: InventoryLog} [transfer out, transfer in]
     */
    public function transfer(Inventory $inventory, int $toLocationId, float $quantity, ?string $remarks = null): array
    {
        if ($toLocationId === (int) $inventory->location_id) {
            throw BusinessRuleException::make('Transfer location must be different from the source location.');
        }

        return DB::transaction(function () use ($inventory, $toLocationId, $quantity, $remarks): array {
            $out = $this->decrease(
                materialId: $inventory->material_id,
                locationId: $inventory->location_id,
                quantity: $quantity,
                type: InventoryMovementType::TransferOut,
                remarks: $remarks,
                transferLocationId: $toLocationId,
            );

            $in = $this->increase(
                materialId: $inventory->material_id,
                locationId: $toLocationId,
                quantity: $quantity,
                type: InventoryMovementType::TransferIn,
                remarks: $remarks,
                transferLocationId: $inventory->location_id,
            );

            return [$out, $in];
        });
    }

    /**
     * Quantity physically on hand at a location.
     */
    public function physicalQuantity(int $materialId, int $locationId): float
    {
        return Money::quantity(
            Inventory::query()
                ->where('material_id', $materialId)
                ->where('location_id', $locationId)
                ->value('quantity') ?? 0
        );
    }

    /**
     * Quantity that may still be promised: on hand minus everything already
     * reserved by pending goods issues at the same location.
     *
     * @param  int|null  $ignoreGoodsIssueId  exclude a goods issue being edited
     */
    public function availableQuantity(int $materialId, int $locationId, ?int $ignoreGoodsIssueId = null): float
    {
        $reserved = GoodsIssueItem::query()
            ->where('material_id', $materialId)
            ->whereHas('goodsIssue', function ($query) use ($locationId, $ignoreGoodsIssueId): void {
                $query->where('status', GoodsIssueStatus::Pending->value)
                    ->where('location_id', $locationId)
                    ->when($ignoreGoodsIssueId, fn ($q) => $q->whereKeyNot($ignoreGoodsIssueId));
            })
            ->sum('qty_to_ship');

        return Money::quantity(max(0, $this->physicalQuantity($materialId, $locationId) - (float) $reserved));
    }

    /**
     * Available quantity for many materials at once, as
     * material id => location id => quantity.
     *
     * Same rule as availableQuantity() but resolved in two queries, for the
     * goods issue screens that need the whole grid.
     *
     * @param  iterable<int>  $materialIds
     * @return array<int, array<int, float>>
     */
    public function availableQuantityMap(iterable $materialIds, ?int $ignoreGoodsIssueId = null): array
    {
        $materialIds = collect($materialIds)->map(fn (mixed $id): int => (int) $id)->unique()->values();

        if ($materialIds->isEmpty()) {
            return [];
        }

        $map = [];

        Inventory::query()
            ->whereIn('material_id', $materialIds)
            ->get(['material_id', 'location_id', 'quantity'])
            ->each(function (Inventory $inventory) use (&$map): void {
                $map[$inventory->material_id][$inventory->location_id] = Money::quantity($inventory->quantity);
            });

        GoodsIssueItem::query()
            ->join('goods_issues', 'goods_issues.id', '=', 'goods_issue_items.goods_issue_id')
            ->whereIn('goods_issue_items.material_id', $materialIds)
            ->where('goods_issues.status', GoodsIssueStatus::Pending->value)
            ->whereNull('goods_issues.deleted_at')
            ->when($ignoreGoodsIssueId, fn ($query) => $query->where('goods_issues.id', '!=', $ignoreGoodsIssueId))
            ->groupBy('goods_issue_items.material_id', 'goods_issues.location_id')
            ->selectRaw('goods_issue_items.material_id as material_id')
            ->selectRaw('goods_issues.location_id as location_id')
            ->selectRaw('COALESCE(SUM(goods_issue_items.qty_to_ship), 0) as reserved')
            ->get()
            ->each(function (object $row) use (&$map): void {
                $onHand = $map[$row->material_id][$row->location_id] ?? 0;

                $map[$row->material_id][$row->location_id] = Money::quantity(max(0, $onHand - (float) $row->reserved));
            });

        return $map;
    }

    /**
     * On-hand quantity per location for a material, keyed by location id.
     *
     * @return array<int, float>
     */
    public function quantitiesByLocation(int $materialId): array
    {
        return Inventory::query()
            ->where('material_id', $materialId)
            ->pluck('quantity', 'location_id')
            ->map(fn (mixed $quantity): float => Money::quantity($quantity))
            ->all();
    }

    /**
     * Lock the inventory row for this material/location, creating or restoring
     * it when needed. Must run inside a transaction.
     */
    private function lockOrCreate(int $materialId, int $locationId): Inventory
    {
        $inventory = Inventory::withTrashed()
            ->where('material_id', $materialId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($inventory === null) {
            return Inventory::create([
                'material_id' => $materialId,
                'location_id' => $locationId,
                'quantity' => 0,
            ]);
        }

        if ($inventory->trashed()) {
            $inventory->restore();
        }

        return $inventory;
    }

    /**
     * Lock an existing inventory row, failing when the material was never
     * stored at that location (there is nothing to take out).
     */
    private function lockExisting(int $materialId, int $locationId): Inventory
    {
        $inventory = Inventory::query()
            ->with(['material', 'location'])
            ->where('material_id', $materialId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($inventory === null) {
            throw InsufficientStockException::for(
                Material::findOrFail($materialId),
                Location::find($locationId),
                0,
                0,
            );
        }

        return $inventory;
    }

    private function writeLog(
        Inventory $inventory,
        InventoryMovementType $type,
        float $before,
        float $change,
        float $after,
        ?Model $reference,
        ?string $remarks,
        ?int $transferLocationId = null,
    ): InventoryLog {
        return InventoryLog::create([
            'movement_code' => InventoryLog::generateMovementCode(),
            'inventory_id' => $inventory->id,
            'material_id' => $inventory->material_id,
            'location_id' => $inventory->location_id,
            'user_id' => Auth::id(),
            'type' => $type->value,
            'quantity_before' => $before,
            'quantity_change' => $change,
            'quantity_after' => $after,
            'transfer_location_id' => $transferLocationId,
            'reference_id' => $reference?->getKey(),
            'reference_type' => $reference?->getMorphClass(),
            'remarks' => $remarks,
        ])->setRelation('inventory', $inventory);
    }

    private function guardPositive(float $quantity): void
    {
        if ($quantity <= 0) {
            throw BusinessRuleException::make('Movement quantity must be greater than zero.');
        }
    }
}
