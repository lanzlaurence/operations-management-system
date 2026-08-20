<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\GeneratesDocumentCode;
use App\Models\Concerns\HasTransactionLogs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A purchase order placed with a vendor.
 *
 * Money columns are derived: they are written by PurchaseOrderService from the
 * line data and never set from a request directly. The status is likewise
 * derived from the goods receipts once the order has been posted.
 *
 * @property PurchaseOrderStatus $status
 * @property DiscountType|null $discount_type
 */
class PurchaseOrder extends Model
{
    use GeneratesDocumentCode;
    use HasFactory;
    use HasTransactionLogs;
    use SoftDeletes;

    protected $fillable = [
        'code', 'vendor_id', 'user_id', 'status',
        'order_date', 'delivery_date', 'reference_no',
        'discount_type', 'discount_amount',
        'total_before_discount', 'total_item_discount',
        'total_net_price', 'total_vat', 'total_gross',
        'total_charges', 'header_discount_total', 'grand_total',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'discount_type' => DiscountType::class,
            'order_date' => 'date',
            'delivery_date' => 'date',
            'discount_amount' => 'decimal:2',
            'total_before_discount' => 'decimal:2',
            'total_item_discount' => 'decimal:2',
            'total_net_price' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'total_charges' => 'decimal:2',
            'header_discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    protected static function documentCodePrefix(): string
    {
        return 'PO-3';
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('line_number');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(PurchaseOrderCharge::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<static>  $query
     * @param  PurchaseOrderStatus|array<int, PurchaseOrderStatus>  $status
     */
    public function scopeWithStatus(Builder $query, PurchaseOrderStatus|array $status): void
    {
        $values = collect(is_array($status) ? $status : [$status])
            ->map(fn (PurchaseOrderStatus $case): string => $case->value)
            ->all();

        $query->whereIn('status', $values);
    }

    /** Orders still expecting stock. */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', PurchaseOrderStatus::openValues());
    }

    /** Orders that count towards purchasing figures (draft excluded). */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', PurchaseOrderStatus::liveValues());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForVendor(Builder $query, int $vendorId): void
    {
        $query->where('vendor_id', $vendorId);
    }

    // ── Derived state ────────────────────────────────────────────────────────

    public function canBeEdited(): bool
    {
        return $this->status->allowsEditing();
    }

    public function canBePosted(): bool
    {
        return $this->status->allowsPosting();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->allowsCancellation();
    }

    public function canBeReverted(): bool
    {
        return $this->status->allowsRevert();
    }

    public function canBeDeleted(): bool
    {
        return $this->status->allowsDeletion();
    }

    public function canCreateGr(): bool
    {
        return $this->status->allowsReceiving();
    }

    /** True once any receipt has booked stock against this order. */
    public function hasReceivedStock(): bool
    {
        return $this->goodsReceipts()
            ->where('status', GoodsReceiptStatus::Completed->value)
            ->exists();
    }

    /**
     * Action availability for the UI, so the buttons on the show page and the
     * server guards are driven by the same rules.
     *
     * @return array<string, bool>
     */
    public function actionFlags(): array
    {
        return [
            'edit' => $this->canBeEdited(),
            'delete' => $this->canBeDeleted() && ! $this->hasReceivedStock(),
            'post' => $this->canBePosted(),
            'cancel' => $this->canBeCancelled(),
            'revert' => $this->canBeReverted() && ! $this->hasReceivedStock(),
            'receive' => $this->canCreateGr(),
        ];
    }
}
