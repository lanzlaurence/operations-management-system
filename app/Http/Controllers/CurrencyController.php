<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:currency-view', only: ['index', 'show']),
            new Middleware('permission:currency-create', only: ['create', 'store']),
            new Middleware('permission:currency-edit', only: ['edit', 'update']),
            new Middleware('permission:currency-delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $currencies = Currency::latest()->get();

        return Inertia::render('currency/index', ['currencies' => $currencies]);
    }

    public function create(): Response
    {
        return Inertia::render('currency/create');
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        Currency::create($request->validated());

        return redirect()->route('currencies.index')
            ->with('success', 'Currency created successfully');
    }

    public function edit(Currency $currency): Response
    {
        return Inertia::render('currency/edit', ['currency' => $currency]);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $currency->update($request->validated());

        return redirect()->route('currencies.index')
            ->with('success', 'Currency updated successfully');
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        $currency->delete();

        return redirect()->route('currencies.index')
            ->with('success', 'Currency deleted successfully');
    }
}
