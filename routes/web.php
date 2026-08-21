<?php

use App\Http\Controllers\FileController;
use App\Livewire\Activity;
use App\Livewire\Auth;
use App\Livewire\Brands;
use App\Livewire\Categories;
use App\Livewire\Charges;
use App\Livewire\Currencies;
use App\Livewire\Customers;
use App\Livewire\Dashboard;
use App\Livewire\GoodsIssues;
use App\Livewire\GoodsReceipts;
use App\Livewire\Inventories;
use App\Livewire\Locations;
use App\Livewire\Materials;
use App\Livewire\Preferences;
use App\Livewire\PurchaseOrders;
use App\Livewire\Roles;
use App\Livewire\SalesOrders;
use App\Livewire\Uoms;
use App\Livewire\Users;
use App\Livewire\Vendors;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('password/change', Auth\ChangePassword::class)->name('password.change');
});

Route::middleware(['auth', 'active', 'verified', 'password.changed'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', Users\Index::class)->name('index')->middleware('permission:user-view');
        Route::get('create', Users\Form::class)->name('create')->middleware('permission:user-create');
        Route::get('{user}/edit', Users\Form::class)->name('edit')->middleware('permission:user-edit');
    });

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', Roles\Index::class)->name('index')->middleware('permission:role-view');
        Route::get('create', Roles\Form::class)->name('create')->middleware('permission:role-create');
        Route::get('{role}/edit', Roles\Form::class)->name('edit')->middleware('permission:role-edit');
    });

    Route::prefix('currencies')->name('currencies.')->group(function () {
        Route::get('/', Currencies\Index::class)->name('index')->middleware('permission:currency-view');
        Route::get('create', Currencies\Form::class)->name('create')->middleware('permission:currency-create');
        Route::get('{currency}/edit', Currencies\Form::class)->name('edit')->middleware('permission:currency-edit');
    });

    Route::get('preferences', Preferences\Edit::class)
        ->name('preferences.index')->middleware('permission:preference-view');

    /*
    |------------------------------------------------------------------
    | Business modules
    |------------------------------------------------------------------
    |
    | One Livewire component per screen, grouped by module. Index, create and
    | edit share a component where the screens are the same shape, so the URL
    | is what tells them apart.
    |
    */
    Route::prefix('brands')->name('brands.')->group(function () {
        Route::get('/', Brands\Index::class)->name('index')->middleware('permission:brand-view');
        Route::get('create', Brands\Form::class)->name('create')->middleware('permission:brand-create');
        Route::get('{brand}/edit', Brands\Form::class)->name('edit')->middleware('permission:brand-edit');
    });

    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', Categories\Index::class)->name('index')->middleware('permission:category-view');
        Route::get('create', Categories\Form::class)->name('create')->middleware('permission:category-create');
        Route::get('{category}/edit', Categories\Form::class)->name('edit')->middleware('permission:category-edit');
    });

    Route::prefix('uoms')->name('uoms.')->group(function () {
        Route::get('/', Uoms\Index::class)->name('index')->middleware('permission:uom-view');
        Route::get('create', Uoms\Form::class)->name('create')->middleware('permission:uom-create');
        Route::get('{uom}/edit', Uoms\Form::class)->name('edit')->middleware('permission:uom-edit');
    });

    Route::prefix('locations')->name('locations.')->group(function () {
        Route::get('/', Locations\Index::class)->name('index')->middleware('permission:location-view');
        Route::get('create', Locations\Form::class)->name('create')->middleware('permission:location-create');
        Route::get('{location}/edit', Locations\Form::class)->name('edit')->middleware('permission:location-edit');
    });

    Route::prefix('inventories')->name('inventories.')->group(function () {
        Route::get('/', Inventories\Index::class)->name('index')->middleware('permission:inventory-view');
        Route::get('manual-adjustment', Inventories\Adjust::class)
            ->name('manual-adjustment')->middleware('permission:inventory-adjust');
        Route::get('{inventory}', Inventories\Show::class)->name('show')->middleware('permission:inventory-view');
    });
    Route::prefix('materials')->name('materials.')->group(function () {
        Route::get('/', Materials\Index::class)->name('index')->middleware('permission:material-view');
        Route::get('create', Materials\Form::class)->name('create')->middleware('permission:material-create');
        Route::get('{material}/purchase-history', Materials\PurchaseHistory::class)
            ->name('purchase-history')->middleware('permission:material-view');
        Route::get('{material}/sales-history', Materials\SalesHistory::class)
            ->name('sales-history')->middleware('permission:material-view');
        Route::get('{material}', Materials\Show::class)->name('show')->middleware('permission:material-view');
        Route::get('{material}/edit', Materials\Form::class)->name('edit')->middleware('permission:material-edit');
    });
    Route::prefix('vendors')->name('vendors.')->group(function () {
        Route::get('/', Vendors\Index::class)->name('index')->middleware('permission:vendor-view');
        Route::get('create', Vendors\Form::class)->name('create')->middleware('permission:vendor-create');
        Route::get('{vendor}', Vendors\Show::class)->name('show')->middleware('permission:vendor-view');
        Route::get('{vendor}/edit', Vendors\Form::class)->name('edit')->middleware('permission:vendor-edit');
    });
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', Customers\Index::class)->name('index')->middleware('permission:customer-view');
        Route::get('create', Customers\Form::class)->name('create')->middleware('permission:customer-create');
        Route::get('{customer}', Customers\Show::class)->name('show')->middleware('permission:customer-view');
        Route::get('{customer}/edit', Customers\Form::class)->name('edit')->middleware('permission:customer-edit');
    });
    Route::prefix('charges')->name('charges.')->group(function () {
        Route::get('/', Charges\Index::class)->name('index')->middleware('permission:charge-view');
        Route::get('create', Charges\Form::class)->name('create')->middleware('permission:charge-create');
        Route::get('{charge}/edit', Charges\Form::class)->name('edit')->middleware('permission:charge-edit');
    });

    /*
    |------------------------------------------------------------------
    | Purchasing
    |------------------------------------------------------------------
    |
    | The status actions (post, cancel, revert, complete) are methods on the
    | document components rather than POST routes: the component owns the
    | confirmation and the service owns the rules.
    |
    */
    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', PurchaseOrders\Index::class)->name('index')->middleware('permission:purchase-order-view');
        Route::get('create', PurchaseOrders\Form::class)->name('create')->middleware('permission:purchase-order-create');
        Route::get('{purchaseOrder}', PurchaseOrders\Show::class)->name('show')->middleware('permission:purchase-order-view');
        Route::get('{purchaseOrder}/edit', PurchaseOrders\Form::class)->name('edit')->middleware('permission:purchase-order-edit');

        // Receiving starts from the order it is receiving against.
        Route::get('{purchaseOrder}/goods-receipts/create', GoodsReceipts\Form::class)
            ->name('goods-receipts.create')->middleware('permission:goods-receipt-create');
    });

    Route::prefix('goods-receipts')->name('goods-receipts.')->group(function () {
        Route::get('/', GoodsReceipts\Index::class)->name('index')->middleware('permission:goods-receipt-view');
        Route::get('{goodsReceipt}', GoodsReceipts\Show::class)->name('show')->middleware('permission:goods-receipt-view');
        Route::get('{goodsReceipt}/edit', GoodsReceipts\Form::class)->name('edit')->middleware('permission:goods-receipt-edit');
    });

    /*
    |------------------------------------------------------------------
    | Sales
    |------------------------------------------------------------------
    */
    Route::prefix('sales-orders')->name('sales-orders.')->group(function () {
        Route::get('/', SalesOrders\Index::class)->name('index')->middleware('permission:sales-order-view');
        Route::get('create', SalesOrders\Form::class)->name('create')->middleware('permission:sales-order-create');
        Route::get('{salesOrder}', SalesOrders\Show::class)->name('show')->middleware('permission:sales-order-view');
        Route::get('{salesOrder}/edit', SalesOrders\Form::class)->name('edit')->middleware('permission:sales-order-edit');

        // Shipping starts from the order it is shipping against.
        Route::get('{salesOrder}/goods-issues/create', GoodsIssues\Form::class)
            ->name('goods-issues.create')->middleware('permission:goods-issue-create');
    });

    Route::prefix('goods-issues')->name('goods-issues.')->group(function () {
        Route::get('/', GoodsIssues\Index::class)->name('index')->middleware('permission:goods-issue-view');
        Route::get('{goodsIssue}', GoodsIssues\Show::class)->name('show')->middleware('permission:goods-issue-view');
        Route::get('{goodsIssue}/edit', GoodsIssues\Form::class)->name('edit')->middleware('permission:goods-issue-edit');
    });

    Route::prefix('activity')->name('activity.')->group(function () {
        Route::get('transaction-log', Activity\TransactionLog::class)
            ->name('transaction-log')->middleware('permission:activity-transaction-log');
        Route::get('inventory-log', Activity\InventoryLog::class)
            ->name('inventory-log')->middleware('permission:activity-inventory-log');
    });

    // Private file access
    Route::get('file', [FileController::class, 'show'])->name('file.show');
});

require __DIR__.'/settings.php';
