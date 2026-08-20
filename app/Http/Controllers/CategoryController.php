<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:category-view', only: ['index', 'show']),
            new Middleware('permission:category-create', only: ['create', 'store']),
            new Middleware('permission:category-edit', only: ['edit', 'update']),
            new Middleware('permission:category-delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $categories = Category::latest()->get();

        return Inertia::render('category/index', ['categories' => $categories]);
    }

    public function create(): Response
    {
        return Inertia::render('category/create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return redirect()->route('categories.index')->with('success', 'Category created successfully');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('category/edit', ['category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('success', 'Category updated successfully');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted successfully');
    }
}
