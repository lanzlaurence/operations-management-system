<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Human readable document numbers in the form `PREFIX` + `yymm` + sequence,
 * for example `PO-32601` + `0007`.
 *
 * The sequence restarts every month and is derived from the highest existing
 * code for that month, including soft deleted rows so a number is never
 * reused. The lookup runs inside a locked read when a transaction is open,
 * which keeps two simultaneous saves from claiming the same number.
 *
 * Implementing models declare their prefix through `documentCodePrefix()`.
 */
trait GeneratesDocumentCode
{
    /** Digits reserved for the monthly sequence. */
    protected static int $codeSequenceLength = 4;

    public static function bootGeneratesDocumentCode(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->code)) {
                $model->code = static::generateCode();
            }
        });
    }

    /**
     * Prefix that identifies the document type, e.g. `PO-3`.
     */
    abstract protected static function documentCodePrefix(): string;

    /**
     * Next available code for the given month (defaults to the current one).
     */
    public static function generateCode(?string $yearMonth = null): string
    {
        $prefix = static::documentCodePrefix() . ($yearMonth ?? now()->format('ym'));

        $query = static::query()
            ->withTrashed()
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('code');

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $lastCode = $query->value('code');

        $next = $lastCode === null
            ? 1
            : ((int) substr($lastCode, -static::$codeSequenceLength)) + 1;

        return $prefix . str_pad((string) $next, static::$codeSequenceLength, '0', STR_PAD_LEFT);
    }
}
