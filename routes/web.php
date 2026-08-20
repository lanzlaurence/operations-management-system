<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GoodsIssueController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('password/change', [PasswordChangeController::class, 'index'])
        ->name('password.change');
    Route::post('password/change', [PasswordChangeController::class, 'update'])
        ->name('password.change.update');
});

Route::middleware(['auth', 'active', 'verified', 'password.changed'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);

    Route::get('preferences', [PreferenceController::class, 'index'])->name('preferences.index');
    Route::post('preferences', [PreferenceController::class, 'update'])->name('preferences.update');

    Route::resource('brands', BrandController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('uoms', UomController::class);
    Route::resource('locations', LocationController::class);
    Route::resource('charges', ChargeController::class);
    // Route::resource('currencies', App\Http\Controllers\CurrencyController::class);

    Route::get('materials/{material}/purchase-history', [MaterialController::class, 'purchaseHistory'])->name('materials.purchase-history');
    Route::get('materials/{material}/sales-history', [MaterialController::class, 'salesHistory'])->name('materials.sales-history');
    Route::resource('materials', MaterialController::class);
    Route::resource('vendors', VendorController::class);
    Route::resource('customers', CustomerController::class);

    Route::get('inventories/manual-adjustment', [InventoryController::class, 'manualAdjustment'])->name('inventories.manual-adjustment');
    Route::post('inventories/manual-adjustment', [InventoryController::class, 'processManualAdjustment'])->name('inventories.manual-adjustment.process');
    // Stock is opened, corrected and moved through the manual adjustment screen,
    // never created directly, so the resource only exposes reading and removal.
    Route::resource('inventories', InventoryController::class)->only(['index', 'show', 'destroy']);

    // Purchase Orders
    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::post('purchase-orders/{purchaseOrder}/post', [PurchaseOrderController::class, 'post'])->name('purchase-orders.post');
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('purchase-orders/{purchaseOrder}/revert', [PurchaseOrderController::class, 'revert'])->name('purchase-orders.revert');

    // Goods Receipts
    Route::resource('goods-receipts', GoodsReceiptController::class);
    Route::post('goods-receipts/{goodsReceipt}/complete', [GoodsReceiptController::class, 'complete'])->name('goods-receipts.complete');
    Route::post('goods-receipts/{goodsReceipt}/cancel', [GoodsReceiptController::class, 'cancel'])->name('goods-receipts.cancel');
    Route::post('goods-receipts/{goodsReceipt}/revert', [GoodsReceiptController::class, 'revert'])->name('goods-receipts.revert');

    // GR from PO
    Route::get('purchase-orders/{purchaseOrder}/goods-receipts/create', [GoodsReceiptController::class, 'create'])->name('purchase-orders.goods-receipts.create');

    // Sales Orders
    Route::resource('sales-orders', SalesOrderController::class);
    Route::post('sales-orders/{salesOrder}/post', [SalesOrderController::class, 'post'])->name('sales-orders.post');
    Route::post('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->name('sales-orders.cancel');
    Route::post('sales-orders/{salesOrder}/revert', [SalesOrderController::class, 'revert'])->name('sales-orders.revert');

    // Goods Issues
    Route::resource('goods-issues', GoodsIssueController::class);
    Route::post('goods-issues/{goodsIssue}/complete', [GoodsIssueController::class, 'complete'])->name('goods-issues.complete');
    Route::post('goods-issues/{goodsIssue}/cancel', [GoodsIssueController::class, 'cancel'])->name('goods-issues.cancel');
    Route::post('goods-issues/{goodsIssue}/revert', [GoodsIssueController::class, 'revert'])->name('goods-issues.revert');

    // GI from SO
    Route::get('sales-orders/{salesOrder}/goods-issues/create', [GoodsIssueController::class, 'create'])->name('sales-orders.goods-issues.create');

    Route::prefix('activity')->name('activity.')->group(function () {
        Route::get('transaction-log', [ActivityController::class, 'transactionLog'])->name('transaction-log');
        Route::get('inventory-log', [ActivityController::class, 'inventoryLog'])->name('inventory-log');
    });

    // Private file access
    Route::get('file', [FileController::class, 'show'])->name('file.show');
});

require __DIR__.'/settings.php';
