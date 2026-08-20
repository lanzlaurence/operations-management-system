<?php

namespace App\Models;

use App\Enums\EntityLogAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Field-level audit entry for a vendor, written by the HasEntityLogs concern.
 *
 * @property EntityLogAction $action
 * @property array<int, array{field: string, old: string, new: string}>|null $changes
 */
class VendorLog extends Model
{
    protected $fillable = ['vendor_id', 'user_id', 'action', 'changes', 'remarks'];

    protected function casts(): array
    {
        return [
            'action' => EntityLogAction::class,
            'changes' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
