<?php

namespace App\Models\Concerns;

use App\Enums\DocumentAction;
use App\Models\TransactionLog;
use App\Services\TransactionLogger;
use BackedEnum;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Audit trail for transactional documents (purchase orders, sales orders,
 * goods receipts, goods issues).
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TransactionLog> $logs
 * @property-read TransactionLog|null $latestLog
 */
trait HasTransactionLogs
{
    /**
     * Full audit trail, oldest first, which is the order the timelines on the
     * show pages render in.
     */
    public function logs(): MorphMany
    {
        return $this->morphMany(TransactionLog::class, 'loggable')->oldest();
    }

    /**
     * The most recent audit entry, handy for "last action by" columns.
     */
    public function latestLog(): MorphOne
    {
        return $this->morphOne(TransactionLog::class, 'loggable')->latestOfMany();
    }

    /**
     * Record an action against this document.
     *
     * Statuses accept enums or plain strings so callers can pass either the
     * document status enum or a value read straight from the database.
     */
    public function recordLog(
        DocumentAction $action,
        BackedEnum|string|null $fromStatus = null,
        BackedEnum|string|null $toStatus = null,
        ?string $remarks = null,
    ): TransactionLog {
        return app(TransactionLogger::class)->log($this, $action, $fromStatus, $toStatus, $remarks);
    }
}
