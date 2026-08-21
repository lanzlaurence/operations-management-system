<?php

namespace App\Models;

use App\Enums\EntityLogAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Field-level audit entry for a customer, written by the HasEntityLogs concern.
 *
 * @property EntityLogAction $action
 * @property array<int, array{field: string, old: string, new: string}>|null $changes
 */
class CustomerLog extends Model
{
    protected $fillable = ['customer_id', 'user_id', 'action', 'changes', 'remarks'];

    protected function casts(): array
    {
        return [
            'action' => EntityLogAction::class,
            'changes' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
