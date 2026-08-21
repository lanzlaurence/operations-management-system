<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\VatType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One material line of a purchase order.
 *
 * `qty_received` is derived from the completed goods receipts by
 * PurchaseOrderService::syncReceiptState(); every money column is written by
 * the line calculator.
 *
 * @property DiscountType|null $discount_type
 * @property VatType|null $vat_type
 */
class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'material_id', 'line_number',
        'qty_ordered', 'qty_received',
        'unit_cost', 'discount_type', 'discount_amount',
        'unit_cost_after_discount', 'net_price',
        'is_vatable', 'vat_type', 'vat_rate', 'vat_price',
        'gross_price', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'vat_type' => VatType::class,
            'qty_ordered' => 'decimal:6',
            'qty_received' => 'decimal:6',
            'unit_cost' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'unit_cost_after_discount' => 'decimal:2',
            'net_price' => 'decimal:2',
            'is_vatable' => 'boolean',
            'vat_rate' => 'decimal:2',
            'vat_price' => 'decimal:2',
            'gross_price' => 'decimal:2',
        ];
    }

    protected $appends = ['qty_remaining'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /**
     * Quantity still to be received, ignoring pending receipts. Use
     * PurchaseOrderService::outstandingQuantities() when pending receipts
     * should be reserved as well.
     */
    public function getQtyRemainingAttribute(): float
    {
        return Money::quantity(max(0, (float) $this->qty_ordered - (float) $this->qty_received));
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->qty_received >= (float) $this->qty_ordered;
    }
}
