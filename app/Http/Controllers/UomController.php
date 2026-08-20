<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUomRequest;
use App\Http\Requests\UpdateUomRequest;
use App\Models\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class UomController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:uom-view', only: ['index', 'show']),
            new Middleware('permission:uom-create', only: ['create', 'store']),
            new Middleware('permission:uom-edit', only: ['edit', 'update']),
            new Middleware('permission:uom-delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $uoms = Uom::latest()->get();

        return Inertia::render('uom/index', ['uoms' => $uoms]);
    }

    public function create(): Response
    {
        return Inertia::render('uom/create');
    }

    public function store(StoreUomRequest $request): RedirectResponse
    {
        Uom::create($request->validated());

        return redirect()->route('uoms.index')->with('success', 'UOM created successfully');
    }

    public function edit(Uom $uom): Response
    {
        return Inertia::render('uom/edit', ['uom' => $uom]);
    }

    public function update(UpdateUomRequest $request, Uom $uom): RedirectResponse
    {
        $uom->update($request->validated());

        return redirect()->route('uoms.index')->with('success', 'UOM updated successfully');
    }

    public function destroy(Uom $uom): RedirectResponse
    {
        $uom->delete();

        return redirect()->route('uoms.index')->with('success', 'UOM deleted successfully');
    }
}
