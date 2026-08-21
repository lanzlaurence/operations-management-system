<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\GeneratesSequentialCode;
use App\Models\Concerns\HasEntityLogs;
use App\Models\Concerns\HasRecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A supplier. Codes run from 200001 upwards.
 *
 * @property RecordStatus $status
 */
class Vendor extends Model
{
    use GeneratesSequentialCode;
    use HasEntityLogs;
    use HasFactory;
    use HasRecordStatus;
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'country', 'state_province', 'city',
        'suburb_barangay', 'postal_code', 'address_line_1', 'address_line_2',
        'payment_terms', 'contact_persons', 'credit_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'contact_persons' => 'array',
            'credit_amount' => 'decimal:2',
        ];
    }

    protected static function sequentialCodePrefix(): string
    {
        return '2';
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VendorLog::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
