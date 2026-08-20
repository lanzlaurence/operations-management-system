<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\GeneratesSequentialCode;
use App\Models\Concerns\HasEntityLogs;
use App\Models\Concerns\HasRecordStatus;
use App\Services\MaterialCostingService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A stock item.
 *
 * `unit_cost` / `unit_price` are the maintained list values, while
 * `avg_unit_cost` / `avg_unit_price` are derived from actual movements by
 * MaterialCostingService.
 *
 * @property RecordStatus $status
 */
class Material extends Model
{
    use GeneratesSequentialCode;
    use HasEntityLogs;
    use HasFactory;
    use HasRecordStatus;
    use SoftDeletes;

    protected $fillable = [
        'code', 'sku', 'name', 'description',
        'weight', 'length', 'width', 'height', 'volume',
        'min_stock_level', 'max_stock_level', 'reorder_level',
        'unit_cost', 'unit_price',
        'avg_unit_cost', 'avg_unit_price',
        'status', 'track_serial_number', 'track_batch_number',
        'brand_id', 'category_id', 'uom_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'weight' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'volume' => 'decimal:2',
            'min_stock_level' => 'integer',
            'max_stock_level' => 'integer',
            'reorder_level' => 'integer',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'avg_unit_cost' => 'decimal:2',
            'avg_unit_price' => 'decimal:2',
            'track_serial_number' => 'boolean',
            'track_batch_number' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $material): void {
            // Until something has actually been bought or sold, the averages
            // are simply the maintained list values.
            $material->avg_unit_cost ??= $material->unit_cost;
            $material->avg_unit_price ??= $material->unit_price;
        });
    }

    protected static function sequentialCodePrefix(): string
    {
        return '3';
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaterialLog::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Eager load everything the material lists and pickers display.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithMasterData(Builder $query): void
    {
        $query->with(['brand', 'category', 'uom']);
    }

    /**
     * Materials at or below their reorder level.
     *
     * @param  Builder<static>  $query
     */
    public function scopeNeedingReorder(Builder $query): void
    {
        $query->where('reorder_level', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inventories WHERE inventories.material_id = materials.id AND inventories.deleted_at IS NULL) <= materials.reorder_level');
    }

    // ── Derived values ───────────────────────────────────────────────────────

    /** Total quantity across every location. */
    public function stockOnHand(): float
    {
        return Money::quantity($this->inventories()->sum('quantity'));
    }

    /** Stock valued at the weighted average purchase cost. */
    public function stockValue(): float
    {
        return Money::round($this->stockOnHand() * (float) $this->avg_unit_cost);
    }

    /**
     * Recompute the weighted average purchase cost from completed receipts.
     *
     * Kept as a model method for convenience; the logic lives in
     * MaterialCostingService.
     */
    public function recalculateAvgUnitCost(): void
    {
        app(MaterialCostingService::class)->syncAverageCost($this);
    }

    /**
     * Recompute the weighted average selling price from completed issues.
     */
    public function recalculateAvgUnitPrice(): void
    {
        app(MaterialCostingService::class)->syncAveragePrice($this);
    }
}
