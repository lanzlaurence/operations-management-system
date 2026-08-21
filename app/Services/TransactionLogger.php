<?php

namespace App\Services;

use App\Enums\DocumentAction;
use App\Models\TransactionLog;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes the transaction log (the audit trail of every document status change).
 *
 * Centralising it means every entry carries the same metadata - acting user,
 * request IP - without each service having to remember, and it gives us one
 * place to change if the trail ever moves to a queue or an external store.
 */
class TransactionLogger
{
    /**
     * Record an action against a document.
     *
     * @param  Model  $document  a model using the HasTransactionLogs concern
     */
    public function log(
        Model $document,
        DocumentAction $action,
        BackedEnum|string|null $fromStatus = null,
        BackedEnum|string|null $toStatus = null,
        ?string $remarks = null,
    ): TransactionLog {
        return TransactionLog::create([
            'user_id' => Auth::id(),
            'action' => $action->value,
            'from_status' => $this->statusValue($fromStatus),
            'to_status' => $this->statusValue($toStatus),
            'remarks' => $remarks,
            'ip_address' => Request::ip(),
            'loggable_id' => $document->getKey(),
            'loggable_type' => $document->getMorphClass(),
        ]);
    }

    /**
     * Record the same action against several documents, e.g. when a cancelled
     * order cascades into its receipts.
     *
     * @param  iterable<Model>  $documents
     * @return array<int, TransactionLog>
     */
    public function logMany(
        iterable $documents,
        DocumentAction $action,
        BackedEnum|string|null $fromStatus = null,
        BackedEnum|string|null $toStatus = null,
        ?string $remarks = null,
    ): array {
        $logs = [];

        foreach ($documents as $document) {
            $logs[] = $this->log($document, $action, $fromStatus, $toStatus, $remarks);
        }

        return $logs;
    }

    private function statusValue(BackedEnum|string|null $status): ?string
    {
        return match (true) {
            $status instanceof BackedEnum => (string) $status->value,
            is_string($status) && $status !== '' => $status,
            default => null,
        };
    }
}
