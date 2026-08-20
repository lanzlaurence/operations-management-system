<?php

namespace App\Models;

use App\Enums\ChargeType;
use App\Enums\ChargeValueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A charge attached to a purchase order.
 *
 * Name, type and value are snapshotted from the master charge at the time the
 * order was saved, so editing the master record never rewrites posted orders.
 *
 * @property ChargeType $type
 * @property ChargeValueType $value_type
 */
class PurchaseOrderCharge extends Model
{
    protected $fillable = [
        'purchase_order_id', 'charge_id', 'name',
        'type', 'value_type', 'value', 'computed_amount',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChargeType::class,
            'value_type' => ChargeValueType::class,
            'value' => 'decimal:2',
            'computed_amount' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    /** Amount signed the way it lands in the grand total. */
    public function signedAmount(): float
    {
        return $this->type->sign() * (float) $this->computed_amount;
    }
}
