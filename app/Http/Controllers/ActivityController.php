<?php

namespace App\Http\Controllers;

use App\Enums\DocumentAction;
use App\Enums\InventoryMovementType;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\InventoryLog;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\TransactionLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only audit screens.
 *
 * Both logs are append-only, so these methods only ever read. Filtering is
 * applied in SQL (indexed by the accompanying migration) and the rows are
 * flattened into exactly the shape the tables render, which keeps the payload
 * small and the frontend free of model plumbing.
 */
class ActivityController extends Controller implements HasMiddleware
{
    /** How many rows the screens load at most. */
    private const MAX_ROWS = 2000;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:activity-transaction-log', only: ['transactionLog']),
            new Middleware('permission:activity-inventory-log', only: ['inventoryLog']),
        ];
    }

    /**
     * Document audit trail, newest first.
     */
    public function transactionLog(Request $request): Response
    {
        $logs = TransactionLog::query()
            ->with(['user:id,name', 'loggable'])
            ->when($request->filled('document_type'), fn ($query) => $query
                ->forDocumentType($this->documentClass($request->string('document_type')->value())))
            ->when($request->filled('action'), fn ($query) => $query
                ->where('action', $request->string('action')->value()))
            ->when($request->boolean('hide_system'), fn ($query) => $query->userActions())
            ->betweenDates($request->query('from'), $request->query('to'))
            ->latest()
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (TransactionLog $log): array => [
                'id' => $log->id,
                'created_at' => $log->created_at,
                'user_name' => $log->user?->name,
                'loggable_type' => $log->loggable_type,
                'loggable_id' => $log->loggable_id,
                'loggable_code' => $log->loggable?->code,
                'document_label' => $log->documentLabel(),
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'remarks' => $log->remarks,
            ]);

        return Inertia::render('activity/transaction-log', [
            'logs' => $logs,
            'filters' => $request->only(['document_type', 'action', 'from', 'to', 'hide_system']),
            'actionOptions' => DocumentAction::options(),
            'documentOptions' => $this->documentOptions(),
        ]);
    }

    /**
     * Stock movement ledger, newest first.
     */
    public function inventoryLog(Request $request): Response
    {
        $logs = InventoryLog::query()
            ->with([
                'material:id,code,name',
                'location:id,name',
                'transferLocation:id,name',
                'user:id,name',
                'inventory:id,code',
            ])
            ->when($request->filled('type'), fn ($query) => $query
                ->where('type', $request->string('type')->value()))
            ->when($request->filled('material_id'), fn ($query) => $query
                ->forMaterial($request->integer('material_id')))
            ->when($request->filled('location_id'), fn ($query) => $query
                ->forLocation($request->integer('location_id')))
            ->betweenDates($request->query('from'), $request->query('to'))
            ->latest()
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (InventoryLog $log): array => [
                'id' => $log->id,
                'movement_code' => $log->movement_code,
                'created_at' => $log->created_at,
                'type' => $log->type->value,
                'type_label' => $log->type->label(),
                'module' => $log->type->module(),
                'inventory_id' => $log->inventory_id,
                'inventory_code' => $log->inventory?->code,
                'material_id' => $log->material_id,
                'material_name' => $log->material?->name,
                'material_code' => $log->material?->code,
                'location_name' => $log->location?->name,
                'quantity_before' => (float) $log->quantity_before,
                'quantity_change' => (float) $log->quantity_change,
                'quantity_after' => (float) $log->quantity_after,
                'transfer_location_name' => $log->transferLocation?->name,
                'user_name' => $log->user?->name,
                'remarks' => $log->remarks,
            ]);

        return Inertia::render('activity/inventory-log', [
            'logs' => $logs,
            'filters' => $request->only(['type', 'material_id', 'location_id', 'from', 'to']),
            'typeOptions' => InventoryMovementType::options(),
        ]);
    }

    /**
     * Map the short document name used in the query string to its model class.
     */
    private function documentClass(?string $type): string
    {
        return match ($type) {
            'PurchaseOrder' => PurchaseOrder::class,
            'GoodsReceipt' => GoodsReceipt::class,
            'SalesOrder' => SalesOrder::class,
            'GoodsIssue' => GoodsIssue::class,
            default => '',
        };
    }

    /**
     * @return array<string, string>
     */
    private function documentOptions(): array
    {
        return [
            'PurchaseOrder' => 'Purchase Order',
            'GoodsReceipt' => 'Goods Receipt',
            'SalesOrder' => 'Sales Order',
            'GoodsIssue' => 'Goods Issue',
        ];
    }
}
