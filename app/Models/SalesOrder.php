<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\GoodsIssueStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Concerns\GeneratesDocumentCode;
use App\Models\Concerns\HasTransactionLogs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sales order confirmed with a customer.
 *
 * Money columns are written by SalesOrderService from the line data; the
 * status is derived from the goods issues once the order has been posted.
 *
 * @property SalesOrderStatus $status
 * @property DiscountType|null $discount_type
 */
class SalesOrder extends Model
{
    use GeneratesDocumentCode;
    use HasFactory;
    use HasTransactionLogs;
    use SoftDeletes;

    protected $fillable = [
        'code', 'customer_id', 'user_id', 'status',
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
            'status' => SalesOrderStatus::class,
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
        return 'SO-1';
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class)->orderBy('line_number');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(SalesOrderCharge::class);
    }

    public function goodsIssues(): HasMany
    {
        return $this->hasMany(GoodsIssue::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<static>  $query
     * @param  SalesOrderStatus|array<int, SalesOrderStatus>  $status
     */
    public function scopeWithStatus(Builder $query, SalesOrderStatus|array $status): void
    {
        $values = collect(is_array($status) ? $status : [$status])
            ->map(fn (SalesOrderStatus $case): string => $case->value)
            ->all();

        $query->whereIn('status', $values);
    }

    /** Orders still owing stock to the customer. */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', SalesOrderStatus::openValues());
    }

    /** Orders that count towards sales figures (draft excluded). */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', SalesOrderStatus::liveValues());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForCustomer(Builder $query, int $customerId): void
    {
        $query->where('customer_id', $customerId);
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

    public function canCreateGi(): bool
    {
        return $this->status->allowsIssuing();
    }

    /** True once any issue has shipped stock against this order. */
    public function hasShippedStock(): bool
    {
        return $this->goodsIssues()
            ->where('status', GoodsIssueStatus::Completed->value)
            ->exists();
    }

    /**
     * Action availability for the UI, mirroring the server-side guards.
     *
     * @return array<string, bool>
     */
    public function actionFlags(): array
    {
        return [
            'edit' => $this->canBeEdited(),
            'delete' => $this->canBeDeleted() && ! $this->hasShippedStock(),
            'post' => $this->canBePosted(),
            'cancel' => $this->canBeCancelled(),
            'revert' => $this->canBeReverted() && ! $this->hasShippedStock(),
            'ship' => $this->canCreateGi(),
        ];
    }
}
