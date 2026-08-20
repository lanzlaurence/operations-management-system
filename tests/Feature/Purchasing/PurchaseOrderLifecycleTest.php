<?php

use App\Data\GoodsReceiptData;
use App\Data\PurchaseOrderData;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Material;
use App\Models\User;
use App\Models\Vendor;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;

/**
 * End-to-end rules of the purchasing flow: what the status derives from, when
 * stock actually moves, and which transitions are refused.
 */
beforeEach(function (): void {
    $this->orders = app(PurchaseOrderService::class);
    $this->receipts = app(GoodsReceiptService::class);

    $this->actingAs(User::factory()->create());

    $this->vendor = Vendor::create(['name' => 'Test Vendor', 'status' => 'active']);
    $this->location = Location::create(['code' => 'WH-T', 'name' => 'Test Warehouse']);
    $this->material = Material::create([
        'name' => 'Test Material',
        'unit_cost' => 100,
        'unit_price' => 150,
        'status' => 'active',
    ]);
});

/**
 * @param  array<int, array<string, mixed>>|null  $items
 */
function draftOrder(?array $items = null): App\Models\PurchaseOrder
{
    return test()->orders->create(PurchaseOrderData::fromArray([
        'vendor_id' => test()->vendor->id,
        'order_date' => today()->toDateString(),
        'items' => $items ?? [[
            'material_id' => test()->material->id,
            'qty_ordered' => 10,
            'unit_cost' => 100,
            'is_vatable' => true,
            'vat_type' => 'exclusive',
            'vat_rate' => 12,
        ]],
    ]));
}

function receiptFor(App\Models\PurchaseOrder $order, float $quantity): App\Models\GoodsReceipt
{
    return test()->receipts->create($order, GoodsReceiptData::fromArray([
        'purchase_order_id' => $order->id,
        'location_id' => test()->location->id,
        'gr_date' => today()->toDateString(),
        'transaction_date' => today()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $order->items->first()->id,
            'qty_to_receive' => $quantity,
        ]],
    ]));
}

it('creates a draft order with computed totals and an audit entry', function (): void {
    $order = draftOrder();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and((float) $order->total_net_price)->toBe(1000.0)
        ->and((float) $order->total_vat)->toBe(120.0)
        ->and((float) $order->grand_total)->toBe(1120.0)
        ->and($order->code)->toStartWith('PO-3')
        ->and($order->logs()->count())->toBe(1);
});

it('refuses to post an order twice', function (): void {
    $order = draftOrder();

    $this->orders->post($order);

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Posted);

    $this->orders->post($order->fresh());
})->throws(BusinessRuleException::class);

it('does not move stock until the receipt is completed', function (): void {
    $order = draftOrder();
    $this->orders->post($order);

    $receipt = receiptFor($order->fresh()->load('items'), 4);

    expect(Inventory::count())->toBe(0)
        ->and($receipt->status)->toBe(GoodsReceiptStatus::Pending)
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::Posted);

    $this->receipts->complete($receipt);

    expect((float) Inventory::first()->quantity)->toBe(4.0)
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and((float) $order->fresh()->items->first()->qty_received)->toBe(4.0);
});

it('marks the order fully received once every line is covered', function (): void {
    $order = draftOrder();
    $this->orders->post($order);

    $this->receipts->complete(receiptFor($order->fresh()->load('items'), 6));
    $this->receipts->complete(receiptFor($order->fresh()->load('items'), 4));

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::FullyReceived)
        ->and((float) Inventory::first()->quantity)->toBe(10.0);
});

it('refuses to receive more than the outstanding quantity', function (): void {
    $order = draftOrder();
    $this->orders->post($order);

    receiptFor($order->fresh()->load('items'), 11);
})->throws(BusinessRuleException::class);

it('reserves quantities held by a pending receipt', function (): void {
    $order = draftOrder();
    $this->orders->post($order);

    receiptFor($order->fresh()->load('items'), 6);

    // 6 are already spoken for, so only 4 remain available.
    expect($this->orders->outstandingQuantities($order->fresh()))
        ->toBe([$order->items->first()->id => 4.0]);
});

it('reverses stock and reopens the quantities when a completed receipt is cancelled', function (): void {
    $order = draftOrder();
    $this->orders->post($order);

    $receipt = $this->receipts->complete(receiptFor($order->fresh()->load('items'), 10));

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::FullyReceived);

    $this->receipts->cancel($receipt);

    expect((float) Inventory::first()->quantity)->toBe(0.0)
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::Posted)
        ->and((float) $order->fresh()->items->first()->qty_received)->toBe(0.0);
});

it('cancels the order together with its receipts and reverses their stock', function (): void {
    $order = draftOrder();
    $this->orders->post($order);
    $this->receipts->complete(receiptFor($order->fresh()->load('items'), 10));

    $this->orders->cancel($order->fresh());

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($order->fresh()->goodsReceipts->pluck('status')->all())->toBe([GoodsReceiptStatus::Cancelled])
        ->and((float) Inventory::first()->quantity)->toBe(0.0);
});

it('refuses to revert an order that already received stock', function (): void {
    $order = draftOrder();
    $this->orders->post($order);
    $this->receipts->complete(receiptFor($order->fresh()->load('items'), 5));

    $this->orders->revert($order->fresh());
})->throws(BusinessRuleException::class);

it('keeps the receipt lines intact when a draft order is edited', function (): void {
    $order = draftOrder();
    $this->orders->post($order);
    receiptFor($order->fresh()->load('items'), 3);
    $this->orders->revert($order->fresh());

    $lineId = $order->fresh()->items->first()->id;

    $this->orders->update($order->fresh(), PurchaseOrderData::fromArray([
        'vendor_id' => $this->vendor->id,
        'order_date' => today()->toDateString(),
        'items' => [[
            'material_id' => $this->material->id,
            'qty_ordered' => 20,
            'unit_cost' => 110,
        ]],
    ]));

    $line = $order->fresh()->items->first();

    expect($line->id)->toBe($lineId)
        ->and((float) $line->qty_ordered)->toBe(20.0)
        ->and((float) $line->unit_cost)->toBe(110.0);
});

it('refuses to edit a draft order below the quantity already received', function (): void {
    $order = draftOrder();
    $this->orders->post($order);
    $this->receipts->complete(receiptFor($order->fresh()->load('items'), 8));

    // Force the order back to draft to isolate the line-level rule.
    $order->fresh()->update(['status' => PurchaseOrderStatus::Draft]);

    $this->orders->update($order->fresh(), PurchaseOrderData::fromArray([
        'vendor_id' => $this->vendor->id,
        'order_date' => today()->toDateString(),
        'items' => [[
            'material_id' => $this->material->id,
            'qty_ordered' => 5,
            'unit_cost' => 100,
        ]],
    ]));
})->throws(BusinessRuleException::class);
