<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Running codes that never restart: master data numbers such as `300001`
 * (materials), `200001` (vendors), `100001` (customers) and `INV-001`
 * (inventory records).
 *
 * The next number is taken from the highest existing code with the same
 * prefix, soft deleted rows included, so codes stay unique for the lifetime of
 * the database. Implementing models declare their prefix and width.
 */
trait GeneratesSequentialCode
{
    public static function bootGeneratesSequentialCode(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->code)) {
                $model->code = static::generateCode();
            }
        });
    }

    /**
     * Prefix that identifies the record type, e.g. `3` or `INV-`.
     */
    abstract protected static function sequentialCodePrefix(): string;

    /**
     * Digits reserved for the running number.
     */
    protected static function sequentialCodeLength(): int
    {
        return 5;
    }

    public static function generateCode(): string
    {
        $prefix = static::sequentialCodePrefix();
        $length = static::sequentialCodeLength();

        $query = static::query()
            ->withTrashed()
            ->where('code', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(code) DESC')
            ->orderByDesc('code');

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $lastCode = $query->value('code');

        $next = $lastCode === null
            ? 1
            : ((int) substr($lastCode, strlen($prefix))) + 1;

        return $prefix . str_pad((string) $next, $length, '0', STR_PAD_LEFT);
    }
}
