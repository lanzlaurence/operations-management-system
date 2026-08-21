<?php

namespace App\Models;

use App\Enums\GoodsIssueStatus;
use App\Models\Concerns\GeneratesDocumentCode;
use App\Models\Concerns\HasTransactionLogs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock shipped out of a location against a sales order.
 *
 * A pending issue reserves stock; completing it deducts it (see
 * GoodsIssueService).
 *
 * @property GoodsIssueStatus $status
 */
class GoodsIssue extends Model
{
    use GeneratesDocumentCode;
    use HasFactory;
    use HasTransactionLogs;
    use SoftDeletes;

    protected $fillable = [
        'code', 'sales_order_id', 'user_id',
        'location_id', 'status', 'gi_date',
        'transaction_date', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsIssueStatus::class,
            'gi_date' => 'date',
            'transaction_date' => 'date',
        ];
    }

    protected static function documentCodePrefix(): string
    {
        return 'GI-2';
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsIssueItem::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', GoodsIssueStatus::Pending->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', GoodsIssueStatus::Completed->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', GoodsIssueStatus::Cancelled->value);
    }

    // ── Derived state ────────────────────────────────────────────────────────

    public function canBeEdited(): bool
    {
        return $this->status->allowsEditing();
    }

    public function canBeDeleted(): bool
    {
        return $this->status->allowsDeletion();
    }

    public function canBeCompleted(): bool
    {
        return $this->status->allowsCompletion() && ! ($this->salesOrder?->status->isCancelled() ?? false);
    }

    public function canBeCancelled(): bool
    {
        return $this->status->allowsCancellation();
    }

    public function canBeReverted(): bool
    {
        return $this->status->allowsRevert() && ! ($this->salesOrder?->status->isCancelled() ?? false);
    }

    public function wasCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    /**
     * @return array<string, bool>
     */
    public function actionFlags(): array
    {
        return [
            'edit' => $this->canBeEdited(),
            'delete' => $this->canBeDeleted(),
            'complete' => $this->canBeCompleted(),
            'cancel' => $this->canBeCancelled(),
            'revert' => $this->canBeReverted(),
        ];
    }
}
