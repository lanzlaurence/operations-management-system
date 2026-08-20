<?php

namespace App\Models\Concerns;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Activation flag shared by master data (materials, vendors, customers,
 * charges, locations, ...).
 *
 * Documents may only reference active records, so every lookup query in the
 * controllers goes through `->active()` rather than repeating a raw
 * `where('status', 'active')`.
 *
 * @property RecordStatus $status
 *
 * @method static Builder<static> active()
 * @method static Builder<static> inactive()
 */
trait HasRecordStatus
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', RecordStatus::Active->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeInactive(Builder $query): void
    {
        $query->where('status', RecordStatus::Inactive->value);
    }

    public function isActive(): bool
    {
        return RecordStatus::parse($this->status)?->isActive() ?? false;
    }
}
