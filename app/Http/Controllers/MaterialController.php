<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Material;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrderItem;
use App\Models\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:material-view', only: ['index', 'show', 'purchaseHistory', 'salesHistory']),
            new Middleware('permission:material-create', only: ['create', 'store']),
            new Middleware('permission:material-edit', only: ['edit', 'update']),
            new Middleware('permission:material-delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $materials = Material::with(['brand', 'category', 'uom'])->latest()->get();

        return Inertia::render('material/index', ['materials' => $materials]);
    }

    public function create(): Response
    {
        $brands = Brand::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $uoms = Uom::orderBy('acronym')->get();

        return Inertia::render('material/create', [
            'brands' => $brands,
            'categories' => $categories,
            'uoms' => $uoms,
        ]);
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        $material = Material::create($request->validated());
        $material->logCreated();

        return redirect()->route('materials.index')
            ->with('success', "Material created successfully with code: {$material->code}");
    }

    public function show(Material $material): Response
    {
        $material->load(['brand', 'category', 'uom', 'logs.user']);

        return Inertia::render('material/show', ['material' => $material]);
    }

    public function edit(Material $material): Response
    {
        $material->load(['brand', 'category', 'uom']);
        $brands = Brand::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $uoms = Uom::orderBy('acronym')->get();

        return Inertia::render('material/edit', [
            'material' => $material,
            'brands' => $brands,
            'categories' => $categories,
            'uoms' => $uoms,
        ]);
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        $old = $material->only($material->getFillable());
        $material->update($request->validated());
        $material->logUpdated($old, $request->validated(), $request->input('update_remarks'));

        return redirect()->route('materials.index')
            ->with('success', "Material {$material->code} updated successfully");
    }

    public function destroy(Material $material): RedirectResponse
    {
        $code = $material->code;
        $material->logDeleted();
        $material->delete();

        return redirect()->route('materials.index')
            ->with('success', "Material {$code} deleted successfully");
    }

    public function purchaseHistory(Material $material): Response
    {
        $material->load('uom');

        $purchaseHistory = PurchaseOrderItem::with(['purchaseOrder.vendor'])
            ->where('material_id', $material->id)
            ->get()
            ->map(fn ($item) => [
                'po_id' => $item->purchaseOrder->id,
                'po_code' => $item->purchaseOrder->code,
                'vendor_id' => $item->purchaseOrder->vendor->id,
                'vendor_code' => $item->purchaseOrder->vendor->code,
                'vendor_name' => $item->purchaseOrder->vendor->name,
                'order_date' => $item->purchaseOrder->order_date->format('Y-m-d'),
                'discount_amount' => (float) $item->discount_amount,
                'unit_cost_after_discount' => (float) $item->unit_cost_after_discount,
                'qty_ordered' => (float) $item->qty_ordered,
                'uom' => $material->uom?->acronym,
                'net_cost' => (float) $item->qty_ordered * (float) $item->unit_cost_after_discount,
            ]);

        return Inertia::render('material/purchase-history', [
            'material' => $this->historyMaterialProps($material),
            'purchaseHistory' => $purchaseHistory,
            'stockByLocation' => $this->stockByLocation($material),
        ]);
    }

    public function salesHistory(Material $material): Response
    {
        $material->load('uom');

        $salesHistory = SalesOrderItem::with(['salesOrder.customer'])
            ->where('material_id', $material->id)
            ->get()
            ->map(fn ($item) => [
                'so_id' => $item->salesOrder->id,
                'so_code' => $item->salesOrder->code,
                'customer_id' => $item->salesOrder->customer->id,
                'customer_code' => $item->salesOrder->customer->code,
                'customer_name' => $item->salesOrder->customer->name,
                'order_date' => $item->salesOrder->order_date->format('Y-m-d'),
                'discount_amount' => (float) $item->discount_amount,
                'unit_price_after_discount' => (float) $item->unit_price_after_discount,
                'qty_ordered' => (float) $item->qty_ordered,
                'uom' => $material->uom?->acronym,
                'net_price' => (float) $item->qty_ordered * (float) $item->unit_price_after_discount,
            ]);

        return Inertia::render('material/sales-history', [
            'material' => $this->historyMaterialProps($material),
            'salesHistory' => $salesHistory,
            'stockByLocation' => $this->stockByLocation($material),
        ]);
    }

    private function historyMaterialProps(Material $material): array
    {
        return [
            'id' => $material->id,
            'code' => $material->code,
            'name' => $material->name,
            'uom' => $material->uom?->acronym,
        ];
    }

    private function stockByLocation(Material $material)
    {
        return Inventory::with('location')
            ->where('material_id', $material->id)
            ->get()
            ->map(fn ($inv) => [
                'location_id' => $inv->location->id,
                'location_name' => $inv->location->name,
                'quantity' => (float) $inv->quantity,
            ]);
    }
}
