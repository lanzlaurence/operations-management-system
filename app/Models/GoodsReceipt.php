<?php

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use App\Models\Concerns\GeneratesDocumentCode;
use App\Models\Concerns\HasTransactionLogs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock received into a location against a purchase order.
 *
 * While pending it is only a plan; completing it is the single moment stock is
 * booked in (see GoodsReceiptService).
 *
 * @property GoodsReceiptStatus $status
 */
class GoodsReceipt extends Model
{
    use GeneratesDocumentCode;
    use HasFactory;
    use HasTransactionLogs;
    use SoftDeletes;

    protected $fillable = [
        'code', 'purchase_order_id', 'user_id',
        'location_id', 'status', 'gr_date',
        'transaction_date', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'gr_date' => 'date',
            'transaction_date' => 'date',
        ];
    }

    protected static function documentCodePrefix(): string
    {
        return 'GR-4';
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
        return $this->hasMany(GoodsReceiptItem::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', GoodsReceiptStatus::Pending->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', GoodsReceiptStatus::Completed->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', GoodsReceiptStatus::Cancelled->value);
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
        return $this->status->allowsCompletion() && ! ($this->purchaseOrder?->status->isCancelled() ?? false);
    }

    public function canBeCancelled(): bool
    {
        return $this->status->allowsCancellation();
    }

    public function canBeReverted(): bool
    {
        return $this->status->allowsRevert() && ! ($this->purchaseOrder?->status->isCancelled() ?? false);
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
