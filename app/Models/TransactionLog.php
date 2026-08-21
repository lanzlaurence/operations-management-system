<?php

namespace App\Models;

use App\Enums\DocumentAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit trail entry for a transactional document.
 *
 * Written exclusively through TransactionLogger, which is what guarantees the
 * user and IP are always filled in. Rows are never updated or deleted by the
 * application.
 *
 * @property DocumentAction $action
 */
class TransactionLog extends Model
{
    protected $fillable = [
        'user_id', 'action',
        'from_status', 'to_status',
        'remarks', 'ip_address',
        'loggable_id', 'loggable_type',
    ];

    protected function casts(): array
    {
        return [
            'action' => DocumentAction::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The document this entry belongs to. Soft deleted documents are included
     * so the trail still resolves after a document is removed.
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<static>  $query
     * @param  DocumentAction|array<int, DocumentAction>  $action
     */
    public function scopeWithAction(Builder $query, DocumentAction|array $action): void
    {
        $values = collect(is_array($action) ? $action : [$action])
            ->map(fn (DocumentAction $case): string => $case->value)
            ->all();

        $query->whereIn('action', $values);
    }

    /**
     * @param  Builder<static>  $query
     * @param  class-string<Model>  $type
     */
    public function scopeForDocumentType(Builder $query, string $type): void
    {
        $query->where('loggable_type', $type);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): void
    {
        $query->when($from, fn (Builder $q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('created_at', '<=', $to));
    }

    /**
     * Hide the derived status-recalculation entries.
     *
     * @param  Builder<static>  $query
     */
    public function scopeUserActions(Builder $query): void
    {
        $query->where('action', '!=', DocumentAction::StatusRecalculated->value);
    }

    /** Short document type name, e.g. `PurchaseOrder`. */
    public function documentType(): string
    {
        return class_basename($this->loggable_type ?? '');
    }

    /** Human readable document type, e.g. "Purchase Order". */
    public function documentLabel(): string
    {
        return trim(preg_replace('/([A-Z])/', ' $1', $this->documentType()) ?? '');
    }
}
