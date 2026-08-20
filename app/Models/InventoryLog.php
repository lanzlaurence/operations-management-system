<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

/**
 * One stock movement.
 *
 * Every row carries the before/change/after triplet, so the ledger can be
 * replayed and reconciled against `inventories.quantity`. Rows are written
 * exclusively by InventoryService and are never edited afterwards.
 *
 * @property InventoryMovementType $type
 */
class InventoryLog extends Model
{
    protected $fillable = [
        'movement_code',
        'inventory_id',
        'material_id',
        'location_id',
        'user_id',
        'type',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'transfer_location_id',
        'reference_id',
        'reference_type',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity_before' => 'decimal:2',
            'quantity_change' => 'decimal:2',
            'quantity_after' => 'decimal:2',
        ];
    }

    /**
     * Movement code in the form `5` + `yymm` + a monthly sequence.
     *
     * Like the document codes, the sequence is read under a lock when a
     * transaction is open so two concurrent movements cannot claim the same
     * number (the column is unique).
     */
    public static function generateMovementCode(): string
    {
        $prefix = '5'.now()->format('ym');

        $query = static::query()
            ->where('movement_code', 'like', $prefix.'%')
            ->orderByDesc('movement_code');

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $lastCode = $query->value('movement_code');

        $next = $lastCode === null ? 1 : ((int) substr($lastCode, -4)) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class)->withTrashed();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function transferLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'transfer_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The document that caused the movement, when there is one. */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<static>  $query
     * @param  InventoryMovementType|array<int, InventoryMovementType>  $type
     */
    public function scopeOfType(Builder $query, InventoryMovementType|array $type): void
    {
        $values = collect(is_array($type) ? $type : [$type])
            ->map(fn (InventoryMovementType $case): string => $case->value)
            ->all();

        $query->whereIn('type', $values);
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
    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): void
    {
        $query->when($from, fn (Builder $q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('created_at', '<=', $to));
    }

    public function isInbound(): bool
    {
        return (float) $this->quantity_change > 0;
    }
}
