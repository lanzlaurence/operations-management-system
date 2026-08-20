<?php

namespace Database\Seeders;

use App\Data\GoodsReceiptData;
use App\Data\PurchaseOrderData;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use Database\Seeders\Concerns\SeedsThroughServices;
use Illuminate\Database\Seeder;

/**
 * Sample purchasing data covering every state a purchase order can reach.
 *
 * Everything is built through PurchaseOrderService and GoodsReceiptService, so
 * the totals, the received quantities, the stock movements, the average costs
 * and the audit trail are exactly what the application would have produced.
 *
 * Scenarios seeded:
 *
 *   1  draft, single line, VAT exclusive
 *   2  draft with line discounts, header discount and charges
 *   3  posted, nothing received yet
 *   4  posted with a pending receipt (stock reserved, not yet booked)
 *   5  partially received from one delivery
 *   6  fully received in one delivery
 *   7  fully received across two deliveries into two locations
 *   8  partially received with a second delivery still pending
 *   9  cancelled straight from posted
 *  10  cancelled after a completed receipt (stock reversed)
 *  11  reverted from posted back to draft
 *  12  cancelled, then reverted back to draft with its receipt reopened
 *  13  mixed VAT inclusive and exclusive lines with a percentage charge
 *  14  three lines, two received in full and one partially
 *  15  large order with serial and batch tracked receipt lines
 */
class PurchaseOrderSeeder extends Seeder
{
    use SeedsThroughServices;

    public function __construct(
        private readonly PurchaseOrderService $orders,
        private readonly GoodsReceiptService $receipts,
    ) {}

    public function run(): void
    {
        $this->asAdministrator(function (): void {
            $this->draftOrders();
            $this->postedOrders();
            $this->receivedOrders();
            $this->cancelledAndRevertedOrders();
        });
    }

    // ── Scenario groups ──────────────────────────────────────────────────────

    private function draftOrders(): void
    {
        // 1 - the simplest possible order, still being prepared.
        $this->createOrder([
            'vendor' => '200001',
            'order_date' => $this->daysAgo(3),
            'delivery_date' => $this->daysAhead(11),
            'reference_no' => 'PR-2001',
            'remarks' => 'Steel restock for the Manila warehouse - awaiting approval.',
            'items' => [
                ['material' => '300001', 'qty' => 120, 'unit_cost' => 250, 'vat' => 'exclusive'],
            ],
        ]);

        // 2 - discounts on the lines, a discount on the header and two charges.
        $this->createOrder([
            'vendor' => '200002',
            'order_date' => $this->daysAgo(2),
            'delivery_date' => $this->daysAhead(14),
            'reference_no' => 'PR-2002',
            'remarks' => 'Negotiated pricing: 5% off cement, PHP 15 off per bag of sand.',
            'discount' => ['percentage', 2.5],
            'charges' => ['Delivery Charge', 'Handling Fee'],
            'items' => [
                ['material' => '300002', 'qty' => 400, 'unit_cost' => 180, 'vat' => 'exclusive', 'discount' => ['percentage', 5]],
                ['material' => '300006', 'qty' => 600, 'unit_cost' => 44.5, 'vat' => 'exclusive', 'discount' => ['fixed', 1.5]],
                ['material' => '300011', 'qty' => 250, 'unit_cost' => 27.5, 'remarks' => 'Non-vatable local supplier.'],
            ],
        ]);

        // 15 - a large draft that will later be received with serials/batches.
        $this->createOrder([
            'vendor' => '200015',
            'order_date' => $this->daysAgo(1),
            'delivery_date' => $this->daysAhead(20),
            'reference_no' => 'PR-2015',
            'remarks' => 'Insulation and fixtures - draft under review.',
            'items' => [
                ['material' => '300004', 'qty' => 60, 'unit_cost' => 1198, 'vat' => 'exclusive'],
                ['material' => '300010', 'qty' => 40, 'unit_cost' => 1195, 'vat' => 'inclusive'],
            ],
        ]);
    }

    private function postedOrders(): void
    {
        // 3 - posted and waiting on the vendor, no receipt raised yet.
        $order = $this->createOrder([
            'vendor' => '200003',
            'order_date' => $this->daysAgo(12),
            'delivery_date' => $this->daysAhead(6),
            'reference_no' => 'PR-2003',
            'remarks' => 'Plywood order confirmed with the vendor.',
            'items' => [
                ['material' => '300003', 'qty' => 150, 'unit_cost' => 620, 'vat' => 'exclusive'],
                ['material' => '300005', 'qty' => 90, 'unit_cost' => 385, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);

        // 4 - posted with a receipt prepared but not yet completed: the
        //     quantities are reserved, no stock has moved.
        $order = $this->createOrder([
            'vendor' => '200004',
            'order_date' => $this->daysAgo(10),
            'delivery_date' => $this->daysAhead(4),
            'reference_no' => 'PR-2004',
            'remarks' => 'Delivery arriving in two batches.',
            'charges' => ['Delivery Charge'],
            'items' => [
                ['material' => '300007', 'qty' => 200, 'unit_cost' => 95, 'vat' => 'exclusive'],
                ['material' => '300012', 'qty' => 40, 'unit_cost' => 2450, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->receive($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(1),
            'remarks' => 'First batch checked in at the gate, pending QC sign-off.',
            'lines' => ['300007' => 120],
        ]);

        // 13 - mixed VAT treatment plus a percentage service charge.
        $order = $this->createOrder([
            'vendor' => '200005',
            'order_date' => $this->daysAgo(9),
            'delivery_date' => $this->daysAhead(8),
            'reference_no' => 'PR-2013',
            'remarks' => 'Mixed VAT treatment: fixtures inclusive, hardware exclusive.',
            'charges' => ['Service Charge (5%)', 'Bulk Order Discount (10%)'],
            'items' => [
                ['material' => '300009', 'qty' => 30, 'unit_cost' => 1848, 'vat' => 'inclusive'],
                ['material' => '300008', 'qty' => 25, 'unit_cost' => 3150, 'vat' => 'exclusive', 'discount' => ['percentage', 3]],
                ['material' => '300013', 'qty' => 80, 'unit_cost' => 178],
            ],
        ]);
        $this->orders->post($order);
    }

    private function receivedOrders(): void
    {
        // 5 - one delivery covering part of the order.
        $order = $this->createOrder([
            'vendor' => '200006',
            'order_date' => $this->daysAgo(24),
            'delivery_date' => $this->daysAgo(4),
            'reference_no' => 'PR-2005',
            'remarks' => 'Vendor short-shipped the first delivery.',
            'items' => [
                ['material' => '300001', 'qty' => 200, 'unit_cost' => 248, 'vat' => 'exclusive'],
                ['material' => '300002', 'qty' => 300, 'unit_cost' => 178, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->receive($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(6),
            'complete' => true,
            'remarks' => 'Partial delivery: steel complete, cement short by 100 bags.',
            'lines' => ['300001' => 200, '300002' => 200],
        ]);

        // 6 - straightforward full delivery.
        $order = $this->createOrder([
            'vendor' => '200007',
            'order_date' => $this->daysAgo(30),
            'delivery_date' => $this->daysAgo(18),
            'reference_no' => 'PR-2006',
            'remarks' => 'Monthly Cebu restock.',
            'charges' => ['Delivery Charge'],
            'items' => [
                ['material' => '300001', 'qty' => 80, 'unit_cost' => 252, 'vat' => 'exclusive'],
                ['material' => '300002', 'qty' => 150, 'unit_cost' => 181, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->receive($order, [
            'location' => 'WH-CEB',
            'date' => $this->daysAgo(18),
            'complete' => true,
            'remarks' => 'Full delivery received in Cebu.',
            'lines' => ['300001' => 80, '300002' => 150],
        ]);

        // 7 - two deliveries into two different locations complete the order.
        $order = $this->createOrder([
            'vendor' => '200008',
            'order_date' => $this->daysAgo(28),
            'delivery_date' => $this->daysAgo(10),
            'reference_no' => 'PR-2007',
            'remarks' => 'Split delivery: half to the north DC, half to Clark.',
            'items' => [
                ['material' => '300006', 'qty' => 800, 'unit_cost' => 43.5, 'vat' => 'exclusive'],
                ['material' => '300011', 'qty' => 400, 'unit_cost' => 27],
            ],
        ]);
        $this->orders->post($order);
        $this->receive($order, [
            'location' => 'DC-NTH',
            'date' => $this->daysAgo(20),
            'complete' => true,
            'remarks' => 'First half delivered to the north distribution centre.',
            'lines' => ['300006' => 400, '300011' => 200],
        ]);
        $this->receive($order, [
            'location' => 'HUB-CLK',
            'date' => $this->daysAgo(10),
            'complete' => true,
            'remarks' => 'Second half delivered to the Clark hub.',
            'lines' => ['300006' => 400, '300011' => 200],
        ]);

        // 8 - part received, part still on a pending receipt.
        $order = $this->createOrder([
            'vendor' => '200009',
            'order_date' => $this->daysAgo(16),
            'delivery_date' => $this->daysAhead(3),
            'reference_no' => 'PR-2008',
            'remarks' => 'Tiles arriving in stages.',
            'items' => [
                ['material' => '300014', 'qty' => 300, 'unit_cost' => 244, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->receive($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(8),
            'complete' => true,
            'remarks' => 'First pallet batch received.',
            'lines' => ['300014' => 120],
        ]);
        $this->receive($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(1),
            'remarks' => 'Second batch on the dock, awaiting inspection.',
            'lines' => ['300014' => 100],
        ]);

        // 14 - three lines: two complete, one short.
        $order = $this->createOrder([
            'vendor' => '200013',
            'order_date' => $this->daysAgo(21),
            'delivery_date' => $this->daysAgo(5),
            'reference_no' => 'PR-2014',
            'remarks' => 'Masonry and carpentry supplies.',
            'charges' => ['Handling Fee'],
            'items' => [
                ['material' => '300014', 'qty' => 100, 'unit_cost' => 244, 'vat' => 'exclusive'],
                ['material' => '300013', 'qty' => 30, 'unit_cost' => 178],
                ['material' => '300011', 'qty' => 400, 'unit_cost' => 27.5],
            ],
        ]);
        $this->orders->post($order);
        $this->receive($order, [
            'location' => 'DC-NTH',
            'date' => $this->daysAgo(5),
            'complete' => true,
            'remarks' => 'Lumber and nails complete, blocks half delivered.',
            'lines' => ['300014' => 100, '300013' => 30, '300011' => 200],
        ]);

        // 15b - receipt carrying serial and batch references.
        $order = $this->createOrder([
            'vendor' => '200010',
            'order_date' => $this->daysAgo(26),
            'delivery_date' => $this->daysAgo(12),
            'reference_no' => 'PR-2010',
            'remarks' => 'Serial tracked equipment and batch tracked adhesive.',
            'items' => [
                ['material' => '300012', 'qty' => 12, 'unit_cost' => 2450, 'vat' => 'exclusive'],
                ['material' => '300015', 'qty' => 90, 'unit_cost' => 320, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->receive($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(12),
            'complete' => true,
            'remarks' => 'Serials logged by the warehouse team.',
            'lines' => [
                '300012' => ['qty' => 12, 'serial_number' => 'SN-2450-0001..0012', 'remarks' => 'Serials verified against the packing list.'],
                '300015' => ['qty' => 90, 'batch_number' => 'BATCH-A7734', 'remarks' => 'Batch expires in 18 months.'],
            ],
        ]);
    }

    private function cancelledAndRevertedOrders(): void
    {
        // 9 - cancelled before anything was received.
        $order = $this->createOrder([
            'vendor' => '200011',
            'order_date' => $this->daysAgo(19),
            'delivery_date' => $this->daysAgo(2),
            'reference_no' => 'PR-2011',
            'remarks' => 'Cancelled: vendor could not meet the delivery window.',
            'items' => [
                ['material' => '300005', 'qty' => 60, 'unit_cost' => 390, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->orders->cancel($order);

        // 10 - received, then cancelled: the receipt is cancelled with the
        //      order and its stock is booked back out.
        $order = $this->createOrder([
            'vendor' => '200012',
            'order_date' => $this->daysAgo(23),
            'delivery_date' => $this->daysAgo(9),
            'reference_no' => 'PR-2012',
            'remarks' => 'Delivered goods rejected by QC and returned to the vendor.',
            'items' => [
                ['material' => '300007', 'qty' => 100, 'unit_cost' => 96, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->receive($order, [
            'location' => 'WH-DAV',
            'date' => $this->daysAgo(14),
            'complete' => true,
            'remarks' => 'Received, later found to be out of specification.',
            'lines' => ['300007' => 100],
        ]);
        $this->orders->cancel($order);

        // 11 - posted, then pulled back to draft for a price correction.
        $order = $this->createOrder([
            'vendor' => '200014',
            'order_date' => $this->daysAgo(7),
            'delivery_date' => $this->daysAhead(9),
            'reference_no' => 'PR-2009',
            'remarks' => 'Reverted to draft: unit costs to be renegotiated.',
            'items' => [
                ['material' => '300003', 'qty' => 70, 'unit_cost' => 640, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->orders->revert($order);

        // 12 - cancelled, then brought back: the pending receipt is reopened
        //      together with the order.
        $order = $this->createOrder([
            'vendor' => '200002',
            'order_date' => $this->daysAgo(15),
            'delivery_date' => $this->daysAhead(5),
            'reference_no' => 'PR-2016',
            'remarks' => 'Cancelled in error, restored after the vendor confirmed.',
            'items' => [
                ['material' => '300002', 'qty' => 200, 'unit_cost' => 179, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->receive($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(2),
            'remarks' => 'Prepared receipt, held while the order was disputed.',
            'lines' => ['300002' => 200],
        ]);
        $this->orders->cancel($order);
        $this->orders->revert($order);
    }

    // ── Builders ─────────────────────────────────────────────────────────────

    /**
     * Create one draft order from a scenario description.
     *
     * @param  array<string, mixed>  $spec
     */
    private function createOrder(array $spec): PurchaseOrder
    {
        [$discountType, $discountAmount] = $spec['discount'] ?? [null, 0];

        return $this->orders->create(PurchaseOrderData::fromArray([
            'vendor_id' => $this->vendorId($spec['vendor']),
            'order_date' => $spec['order_date'],
            'delivery_date' => $spec['delivery_date'] ?? null,
            'reference_no' => $spec['reference_no'] ?? null,
            'discount_type' => $discountType,
            'discount_amount' => $discountAmount,
            'remarks' => $spec['remarks'] ?? null,
            'items' => $this->itemRows($spec['items']),
            'charges' => $this->chargeRows($spec['charges'] ?? []),
        ]));
    }

    /**
     * Item payload rows, resolving material codes and VAT shorthand.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function itemRows(array $items): array
    {
        $rows = [];

        foreach ($items as $item) {
            $materialId = $this->materialId($item['material']);

            if ($materialId === null) {
                continue;
            }

            [$discountType, $discountAmount] = $item['discount'] ?? [null, 0];

            $rows[] = [
                'material_id' => $materialId,
                'qty_ordered' => $item['qty'],
                'unit_cost' => $item['unit_cost'],
                'discount_type' => $discountType,
                'discount_amount' => $discountAmount,
                'is_vatable' => isset($item['vat']),
                'vat_type' => $item['vat'] ?? null,
                'vat_rate' => isset($item['vat']) ? 12 : 0,
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Raise a receipt against an order and optionally complete it.
     *
     * `lines` maps a material code to either a plain quantity or an array with
     * the quantity plus serial/batch/remarks.
     *
     * @param  array<string, mixed>  $spec
     */
    private function receive(PurchaseOrder $order, array $spec): ?GoodsReceipt
    {
        $order->refresh()->load('items');

        $items = [];

        foreach ($spec['lines'] as $materialCode => $line) {
            $orderItem = $order->items->firstWhere('material_id', $this->materialId($materialCode));

            if ($orderItem === null) {
                continue;
            }

            $line = is_array($line) ? $line : ['qty' => $line];

            $items[] = [
                'purchase_order_item_id' => $orderItem->id,
                'qty_to_receive' => $line['qty'],
                'serial_number' => $line['serial_number'] ?? null,
                'batch_number' => $line['batch_number'] ?? null,
                'remarks' => $line['remarks'] ?? null,
            ];
        }

        if ($items === []) {
            return null;
        }

        $receipt = $this->receipts->create($order, GoodsReceiptData::fromArray([
            'purchase_order_id' => $order->id,
            'location_id' => $this->locationId($spec['location']),
            'gr_date' => $spec['date'],
            'transaction_date' => $spec['date'],
            'remarks' => $spec['remarks'] ?? null,
            'items' => $items,
        ]));

        if ($spec['complete'] ?? false) {
            $receipt = $this->receipts->complete($receipt);
        }

        return $receipt;
    }
}
