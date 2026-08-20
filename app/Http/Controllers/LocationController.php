<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:location-view', only: ['index', 'show']),
            new Middleware('permission:location-create', only: ['create', 'store']),
            new Middleware('permission:location-edit', only: ['edit', 'update']),
            new Middleware('permission:location-delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $locations = Location::latest()->get();

        return Inertia::render('location/index', ['locations' => $locations]);
    }

    public function create(): Response
    {
        return Inertia::render('location/create');
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        return redirect()->route('locations.index')->with('success', 'Location created successfully');
    }

    public function edit(Location $location): Response
    {
        return Inertia::render('location/edit', ['location' => $location]);
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return redirect()->route('locations.index')->with('success', 'Location updated successfully');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return redirect()->route('locations.index')->with('success', 'Location deleted successfully');
    }
}
