<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\VatType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One material line of a sales order.
 *
 * `qty_shipped` is derived from the completed goods issues by
 * SalesOrderService::syncIssueState(); every money column is written by the
 * line calculator.
 *
 * @property DiscountType|null $discount_type
 * @property VatType|null $vat_type
 */
class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id', 'material_id', 'line_number',
        'qty_ordered', 'qty_shipped',
        'unit_price', 'discount_type', 'discount_amount',
        'unit_price_after_discount', 'net_price',
        'is_vatable', 'vat_type', 'vat_rate', 'vat_price',
        'gross_price', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'vat_type' => VatType::class,
            'qty_ordered' => 'decimal:6',
            'qty_shipped' => 'decimal:6',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'unit_price_after_discount' => 'decimal:2',
            'net_price' => 'decimal:2',
            'is_vatable' => 'boolean',
            'vat_rate' => 'decimal:2',
            'vat_price' => 'decimal:2',
            'gross_price' => 'decimal:2',
        ];
    }

    protected $appends = ['qty_remaining'];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function goodsIssueItems(): HasMany
    {
        return $this->hasMany(GoodsIssueItem::class);
    }

    /**
     * Quantity still to be shipped, ignoring pending issues. Use
     * SalesOrderService::outstandingQuantities() when pending issues should be
     * reserved as well.
     */
    public function getQtyRemainingAttribute(): float
    {
        return Money::quantity(max(0, (float) $this->qty_ordered - (float) $this->qty_shipped));
    }

    public function isFullyShipped(): bool
    {
        return (float) $this->qty_shipped >= (float) $this->qty_ordered;
    }
}
