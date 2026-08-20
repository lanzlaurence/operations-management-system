<?php

namespace App\Http\Controllers;

use App\Enums\StockAdjustmentAction;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\StoreManualAdjustmentRequest;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Location;
use App\Models\Material;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock balances and the manual adjustment screen.
 *
 * Quantities are never written here: each action delegates to
 * InventoryService, which locks the row, refuses to go negative and writes the
 * matching movement log.
 */
class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Inventory::class);

        return Inertia::render('inventory/index', [
            'inventories' => Inventory::query()
                ->with(['material.uom', 'location:id,code,name'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Inventory $inventory): Response
    {
        $this->authorize('view', $inventory);

        $inventory->load(['material.uom', 'location']);

        return Inertia::render('inventory/show', [
            'inventory' => $inventory,
            'logs' => InventoryLog::query()
                ->with(['user:id,name', 'location:id,name', 'transferLocation:id,name'])
                ->where('inventory_id', $inventory->id)
                ->latest()
                ->get(),
        ]);
    }

    public function destroy(Inventory $inventory): RedirectResponse
    {
        $this->authorize('delete', $inventory);

        if (! $inventory->isEmpty()) {
            throw BusinessRuleException::make('Cannot delete a stock record that still holds quantity.')
                ->redirectTo('inventories.index');
        }

        $inventory->delete();

        return redirect()
            ->route('inventories.index')
            ->with('success', "Stock record {$inventory->code} deleted successfully.");
    }

    /**
     * Manual adjustment form: opening stock, corrections and transfers.
     */
    public function manualAdjustment(): Response
    {
        $this->authorize('adjust', Inventory::class);

        return Inertia::render('inventory/manual-adjustment', [
            'materials' => Material::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'locations' => Location::query()->orderBy('name')->get(['id', 'code', 'name']),
            'inventories' => Inventory::query()
                ->with(['material:id,code,name', 'location:id,code,name'])
                ->get(['id', 'material_id', 'location_id', 'quantity']),
            'actions' => StockAdjustmentAction::options(),
        ]);
    }

    public function processManualAdjustment(StoreManualAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->toData();

        match ($data->action) {
            StockAdjustmentAction::Initial => $this->inventory->initialise(
                materialId: (int) $data->materialId,
                locationId: $data->locationId,
                quantity: $data->quantity,
                remarks: $data->remarks,
            ),
            StockAdjustmentAction::Adjust => $this->inventory->adjustTo(
                inventory: Inventory::findOrFail($data->inventoryId),
                quantity: $data->quantity,
                remarks: $data->remarks,
            ),
            StockAdjustmentAction::Transfer => $this->inventory->transfer(
                inventory: Inventory::findOrFail($data->inventoryId),
                toLocationId: (int) $data->transferLocationId,
                quantity: $data->quantity,
                remarks: $data->remarks,
            ),
        };

        return redirect()
            ->route('inventories.index')
            ->with('success', $data->action->label().' processed successfully.');
    }
}
