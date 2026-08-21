<?php

namespace App\Models;

use App\Models\Concerns\GeneratesSequentialCode;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The stock balance of one material at one location.
 *
 * `quantity` is only ever written by InventoryService, which locks the row and
 * writes the matching InventoryLog entry in the same transaction.
 */
class Inventory extends Model
{
    use GeneratesSequentialCode;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'material_id',
        'location_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    protected static function sequentialCodePrefix(): string
    {
        return 'INV-';
    }

    protected static function sequentialCodeLength(): int
    {
        return 3;
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForMaterial(Builder $query, int $materialId): void
    {
        $query->where('material_id', $materialId);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForLocation(Builder $query, int $locationId): void
    {
        $query->where('location_id', $locationId);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeInStock(Builder $query): void
    {
        $query->where('quantity', '>', 0);
    }

    public function isEmpty(): bool
    {
        return Money::quantity($this->quantity) <= 0;
    }

    /** Balance valued at the material's weighted average cost. */
    public function value(): float
    {
        return Money::round((float) $this->quantity * (float) ($this->material?->avg_unit_cost ?? 0));
    }
}
