<?php

namespace App\Models\Concerns;

use App\Enums\EntityLogAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * Field-level audit trail for master data (materials, vendors, customers).
 *
 * Unlike the transactional documents, these records have no status flow, so
 * what matters is which attributes changed. `logUpdated()` stores a compact
 * diff of only the fields that actually moved.
 *
 * Implementing models expose a `logs()` HasMany relation to their own log
 * table (material_logs, vendor_logs, customer_logs).
 */
trait HasEntityLogs
{
    abstract public function logs(): HasMany;

    public function logCreated(?string $remarks = null): Model
    {
        return $this->writeLog(EntityLogAction::Created, null, $remarks);
    }

    /**
     * Record an update together with the fields that changed.
     *
     * @param  array<string, mixed>  $before  attributes before the update
     * @param  array<string, mixed>  $after  attributes that were written
     */
    public function logUpdated(array $before, array $after, ?string $remarks = null): Model
    {
        $changes = $this->diff($before, $after);

        return $this->writeLog(EntityLogAction::Updated, $changes ?: null, $remarks);
    }

    public function logDeleted(?string $remarks = null): Model
    {
        return $this->writeLog(EntityLogAction::Deleted, null, $remarks);
    }

    public function logRestored(?string $remarks = null): Model
    {
        return $this->writeLog(EntityLogAction::Restored, null, $remarks);
    }

    /**
     * Field-by-field diff of two attribute sets.
     *
     * Values are normalised first so that `"10.00"` and `10` or `null` and `''`
     * are not reported as changes, and arrays (contact persons, ...) are
     * compared by their JSON representation.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, array{field: string, old: string, new: string}>
     */
    protected function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $newValue) {
            $old = $this->normalise($before[$field] ?? null);
            $new = $this->normalise($newValue);

            if (is_numeric($old) && is_numeric($new)) {
                $old = (string) (float) $old;
                $new = (string) (float) $new;
            }

            if ($old !== $new) {
                $changes[] = ['field' => $field, 'old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /**
     * Reduce any attribute value to a comparable string.
     */
    protected function normalise(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => json_encode($value) ?: '',
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => trim((string) $value),
        };
    }

    /**
     * @param  array<int, array{field: string, old: string, new: string}>|null  $changes
     */
    protected function writeLog(EntityLogAction $action, ?array $changes, ?string $remarks): Model
    {
        return $this->logs()->create([
            'user_id' => Auth::id(),
            'action' => $action->value,
            'changes' => $changes,
            'remarks' => $remarks,
        ]);
    }
}
