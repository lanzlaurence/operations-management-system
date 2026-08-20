<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a goods issue.
 *
 * `qty_ordered`, `qty_shipped` and `unit_price` snapshot the sales order line
 * at the moment of shipping; `qty_to_ship` is the quantity this issue moves
 * and the only figure inventory is posted from.
 */
class GoodsIssueItem extends Model
{
    protected $fillable = [
        'goods_issue_id', 'sales_order_item_id', 'material_id',
        'qty_ordered', 'qty_shipped', 'qty_to_ship', 'qty_remaining',
        'unit_price', 'serial_number', 'batch_number', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:6',
            'qty_shipped' => 'decimal:6',
            'qty_to_ship' => 'decimal:6',
            'qty_remaining' => 'decimal:6',
            'unit_price' => 'decimal:2',
        ];
    }

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** Value this line takes out of inventory once the issue is completed. */
    public function lineValue(): float
    {
        return Money::round((float) $this->qty_to_ship * (float) $this->unit_price);
    }
}
