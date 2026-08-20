<?php

namespace App\Models;

use App\Enums\EntityLogAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Field-level audit entry for a material, written by the HasEntityLogs concern.
 *
 * @property EntityLogAction $action
 * @property array<int, array{field: string, old: string, new: string}>|null $changes
 */
class MaterialLog extends Model
{
    protected $fillable = ['material_id', 'user_id', 'action', 'changes', 'remarks'];

    protected function casts(): array
    {
        return [
            'action' => EntityLogAction::class,
            'changes' => 'array',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
