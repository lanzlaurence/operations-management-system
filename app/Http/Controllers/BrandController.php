<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:brand-view', only: ['index', 'show']),
            new Middleware('permission:brand-create', only: ['create', 'store']),
            new Middleware('permission:brand-edit', only: ['edit', 'update']),
            new Middleware('permission:brand-delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $brands = Brand::latest()->get();

        return Inertia::render('brand/index', ['brands' => $brands]);
    }

    public function create(): Response
    {
        return Inertia::render('brand/create');
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        Brand::create($request->validated());

        return redirect()->route('brands.index')->with('success', 'Brand created successfully');
    }

    public function edit(Brand $brand): Response
    {
        return Inertia::render('brand/edit', ['brand' => $brand]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($request->validated());

        return redirect()->route('brands.index')->with('success', 'Brand updated successfully');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Brand deleted successfully');
    }
}
