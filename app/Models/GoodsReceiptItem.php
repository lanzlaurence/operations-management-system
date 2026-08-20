<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a goods receipt.
 *
 * `qty_ordered`, `qty_received` and `unit_cost` are a snapshot of the purchase
 * order line at the moment of receiving, which is what makes the receipt a
 * standalone historical document. `qty_to_receive` is the quantity this
 * receipt moves and the only figure inventory is posted from.
 */
class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id', 'purchase_order_item_id', 'material_id',
        'qty_ordered', 'qty_received', 'qty_to_receive', 'qty_remaining',
        'unit_cost', 'serial_number', 'batch_number', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:6',
            'qty_received' => 'decimal:6',
            'qty_to_receive' => 'decimal:6',
            'qty_remaining' => 'decimal:6',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** Value this line adds to inventory once the receipt is completed. */
    public function lineValue(): float
    {
        return Money::round((float) $this->qty_to_receive * (float) $this->unit_cost);
    }
}
