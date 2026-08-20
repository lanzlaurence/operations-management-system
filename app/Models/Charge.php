<?php

namespace App\Models;

use App\Enums\ChargeType;
use App\Enums\ChargeValueType;
use App\Enums\RecordStatus;
use App\Models\Concerns\HasRecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable document charge: freight, handling, withholding tax, rebate, ...
 *
 * Documents snapshot these values when they are attached, so changing a charge
 * here only affects documents saved from that point on.
 *
 * @property ChargeType $type
 * @property ChargeValueType $value_type
 * @property RecordStatus $status
 */
class Charge extends Model
{
    use HasFactory;
    use HasRecordStatus;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'value_type',
        'value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChargeType::class,
            'value_type' => ChargeValueType::class,
            'status' => RecordStatus::class,
            'value' => 'decimal:2',
        ];
    }

    /** Resolve this charge against a document base amount. */
    public function computeOn(float $base): float
    {
        return $this->value_type->computeOn($base, (float) $this->value);
    }
}
