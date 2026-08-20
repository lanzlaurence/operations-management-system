<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChargeRequest;
use App\Http\Requests\UpdateChargeRequest;
use App\Models\Charge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ChargeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:charge-view', only: ['index', 'show']),
            new Middleware('permission:charge-create', only: ['create', 'store']),
            new Middleware('permission:charge-edit', only: ['edit', 'update']),
            new Middleware('permission:charge-delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $charges = Charge::latest()->get();

        return Inertia::render('charge/index', ['charges' => $charges]);
    }

    public function create(): Response
    {
        return Inertia::render('charge/create');
    }

    public function store(StoreChargeRequest $request): RedirectResponse
    {
        $charge = Charge::create($request->validated());

        return redirect()->route('charges.index')
            ->with('success', 'Charge created successfully');
    }

    public function edit(Charge $charge): Response
    {
        return Inertia::render('charge/edit', ['charge' => $charge]);
    }

    public function update(UpdateChargeRequest $request, Charge $charge): RedirectResponse
    {
        $charge->update($request->validated());

        return redirect()->route('charges.index')
            ->with('success', 'Charge updated successfully');
    }

    public function destroy(Charge $charge): RedirectResponse
    {
        $charge->delete();

        return redirect()->route('charges.index')
            ->with('success', 'Charge deleted successfully');
    }
}
