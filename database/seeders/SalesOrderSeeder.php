<?php

namespace Database\Seeders;

use App\Data\GoodsIssueData;
use App\Data\SalesOrderData;
use App\Models\GoodsIssue;
use App\Models\SalesOrder;
use App\Services\GoodsIssueService;
use App\Services\InventoryService;
use App\Services\SalesOrderService;
use App\Support\Money;
use Database\Seeders\Concerns\SeedsThroughServices;
use Illuminate\Database\Seeder;

/**
 * Sample sales data covering every state a sales order can reach.
 *
 * Built through SalesOrderService and GoodsIssueService, so the totals,
 * shipped quantities, stock deductions, average selling prices and audit trail
 * match what the application itself would produce. Quantities are checked
 * against the stock available at the chosen location before a line is added,
 * which keeps the seeder working whatever the opening balances happen to be.
 *
 * Scenarios seeded:
 *
 *   1  draft, single line
 *   2  draft with line discounts, header discount and a discount charge
 *   3  posted, nothing shipped yet
 *   4  posted with a pending issue (stock reserved, not yet deducted)
 *   5  partially shipped from one delivery
 *   6  fully shipped in one delivery
 *   7  fully shipped across two deliveries from two locations
 *   8  partially shipped with a second delivery still pending
 *   9  cancelled straight from posted
 *  10  cancelled after a completed issue (stock returned)
 *  11  reverted from posted back to draft
 *  12  cancelled, then reverted back to draft with its issue reopened
 *  13  mixed VAT inclusive and exclusive lines with percentage charges
 *  14  three lines, two shipped in full and one partially
 *  15  issue carrying serial and batch references
 */
class SalesOrderSeeder extends Seeder
{
    use SeedsThroughServices;

    public function __construct(
        private readonly SalesOrderService $orders,
        private readonly GoodsIssueService $issues,
        private readonly InventoryService $inventory,
    ) {}

    public function run(): void
    {
        $this->asAdministrator(function (): void {
            $this->draftOrders();
            $this->postedOrders();
            $this->shippedOrders();
            $this->cancelledAndRevertedOrders();
        });
    }

    // ── Scenario groups ──────────────────────────────────────────────────────

    private function draftOrders(): void
    {
        // 1 - quotation just turned into an order, still a draft.
        $this->createOrder([
            'customer' => '100001',
            'order_date' => $this->daysAgo(2),
            'delivery_date' => $this->daysAhead(10),
            'reference_no' => 'SQ-3001',
            'remarks' => 'Steel rods for the Ortigas project - pending customer PO.',
            'items' => [
                ['material' => '300001', 'qty' => 60, 'unit_price' => 350, 'vat' => 'exclusive'],
            ],
        ]);

        // 2 - trade pricing: line discounts, a header discount and a rebate.
        $this->createOrder([
            'customer' => '100002',
            'order_date' => $this->daysAgo(1),
            'delivery_date' => $this->daysAhead(12),
            'reference_no' => 'SQ-3002',
            'remarks' => 'Contractor pricing with a loyalty rebate.',
            'discount' => ['percentage', 3],
            'charges' => ['Delivery Charge', 'Loyalty Discount (3%)'],
            'items' => [
                ['material' => '300002', 'qty' => 150, 'unit_price' => 260, 'vat' => 'exclusive', 'discount' => ['percentage', 4]],
                ['material' => '300006', 'qty' => 200, 'unit_price' => 65, 'vat' => 'exclusive', 'discount' => ['fixed', 2]],
                ['material' => '300011', 'qty' => 120, 'unit_price' => 42, 'remarks' => 'Non-vatable government project line.'],
            ],
        ]);

        // 13 - mixed VAT treatment with percentage charges both ways.
        $this->createOrder([
            'customer' => '100013',
            'order_date' => $this->daysAgo(4),
            'delivery_date' => $this->daysAhead(15),
            'reference_no' => 'SQ-3013',
            'remarks' => 'Retail fit-out: fixtures VAT inclusive, hardware exclusive.',
            'charges' => ['Installation Fee', 'Senior Citizen Discount (20%)'],
            'items' => [
                ['material' => '300009', 'qty' => 10, 'unit_price' => 2450, 'vat' => 'inclusive'],
                ['material' => '300010', 'qty' => 8, 'unit_price' => 1650, 'vat' => 'exclusive'],
                ['material' => '300013', 'qty' => 25, 'unit_price' => 240],
            ],
        ]);
    }

    private function postedOrders(): void
    {
        // 3 - confirmed with the customer, nothing picked yet.
        $order = $this->createOrder([
            'customer' => '100003',
            'order_date' => $this->daysAgo(11),
            'delivery_date' => $this->daysAhead(5),
            'reference_no' => 'SO-REF-3003',
            'remarks' => 'Plywood and hardware for a Quezon City fit-out.',
            'items' => [
                ['material' => '300003', 'qty' => 40, 'unit_price' => 850, 'vat' => 'exclusive'],
                ['material' => '300005', 'qty' => 30, 'unit_price' => 520, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);

        // 4 - picking list prepared: stock is reserved but still on hand.
        $order = $this->createOrder([
            'customer' => '100004',
            'order_date' => $this->daysAgo(9),
            'delivery_date' => $this->daysAhead(3),
            'reference_no' => 'SO-REF-3004',
            'remarks' => 'Truck scheduled for tomorrow morning.',
            'charges' => ['Delivery Charge'],
            'items' => [
                ['material' => '300006', 'qty' => 300, 'unit_price' => 68, 'vat' => 'exclusive'],
                ['material' => '300007', 'qty' => 80, 'unit_price' => 145, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->ship($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(1),
            'remarks' => 'Picked and staged at the loading bay.',
            'lines' => ['300006' => 300, '300007' => 80],
        ]);
    }

    private function shippedOrders(): void
    {
        // 5 - first delivery covered part of the order.
        $order = $this->createOrder([
            'customer' => '100005',
            'order_date' => $this->daysAgo(22),
            'delivery_date' => $this->daysAgo(3),
            'reference_no' => 'SO-REF-3005',
            'remarks' => 'Customer accepted a staged delivery.',
            'items' => [
                ['material' => '300001', 'qty' => 100, 'unit_price' => 355, 'vat' => 'exclusive'],
                ['material' => '300002', 'qty' => 200, 'unit_price' => 258, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->ship($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(5),
            'complete' => true,
            'remarks' => 'Steel delivered in full, cement half delivered.',
            'lines' => ['300001' => 100, '300002' => 100],
        ]);

        // 6 - single full delivery.
        $order = $this->createOrder([
            'customer' => '100006',
            'order_date' => $this->daysAgo(27),
            'delivery_date' => $this->daysAgo(16),
            'reference_no' => 'SO-REF-3006',
            'remarks' => 'Cebu branch order, delivered complete.',
            'charges' => ['Delivery Charge'],
            'items' => [
                ['material' => '300001', 'qty' => 50, 'unit_price' => 360, 'vat' => 'exclusive'],
                ['material' => '300002', 'qty' => 120, 'unit_price' => 262, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->ship($order, [
            'location' => 'WH-CEB',
            'date' => $this->daysAgo(16),
            'complete' => true,
            'remarks' => 'Delivered complete from the Cebu warehouse.',
            'lines' => ['300001' => 50, '300002' => 120],
        ]);

        // 7 - shipped in two deliveries out of two warehouses.
        $order = $this->createOrder([
            'customer' => '100007',
            'order_date' => $this->daysAgo(25),
            'delivery_date' => $this->daysAgo(8),
            'reference_no' => 'SO-REF-3007',
            'remarks' => 'Split shipment: Manila first, Davao to follow.',
            'items' => [
                ['material' => '300006', 'qty' => 500, 'unit_price' => 66, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->ship($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(15),
            'complete' => true,
            'remarks' => 'First 300 bags out of Manila.',
            'lines' => ['300006' => 300],
        ]);
        $this->ship($order, [
            'location' => 'WH-DAV',
            'date' => $this->daysAgo(8),
            'complete' => true,
            'remarks' => 'Remaining 200 bags out of Davao.',
            'lines' => ['300006' => 200],
        ]);

        // 8 - part shipped, part still on a pending issue.
        $order = $this->createOrder([
            'customer' => '100008',
            'order_date' => $this->daysAgo(14),
            'delivery_date' => $this->daysAhead(2),
            'reference_no' => 'SO-REF-3008',
            'remarks' => 'Tiles going out in two truckloads.',
            'items' => [
                ['material' => '300014', 'qty' => 180, 'unit_price' => 330, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->ship($order, [
            'location' => 'HUB-CLK',
            'date' => $this->daysAgo(7),
            'complete' => true,
            'remarks' => 'First truckload dispatched from Clark.',
            'lines' => ['300014' => 100],
        ]);
        $this->ship($order, [
            'location' => 'HUB-CLK',
            'date' => $this->daysAhead(1),
            'remarks' => 'Second truckload booked for tomorrow.',
            'lines' => ['300014' => 80],
        ]);

        // 14 - three lines: two complete, one short.
        $order = $this->createOrder([
            'customer' => '100014',
            'order_date' => $this->daysAgo(20),
            'delivery_date' => $this->daysAgo(6),
            'reference_no' => 'SO-REF-3014',
            'remarks' => 'Mixed order for a subdivision project.',
            'charges' => ['Handling Fee', 'Bulk Order Discount (10%)'],
            'items' => [
                ['material' => '300011', 'qty' => 300, 'unit_price' => 45],
                ['material' => '300013', 'qty' => 40, 'unit_price' => 245],
                ['material' => '300002', 'qty' => 100, 'unit_price' => 259, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->ship($order, [
            'location' => 'DC-NTH',
            'date' => $this->daysAgo(6),
            'complete' => true,
            'remarks' => 'Blocks and nails complete, cement partly delivered.',
            'lines' => ['300011' => 300, '300013' => 30, '300002' => 60],
        ]);

        // 15 - serial and batch references captured on the issue.
        $order = $this->createOrder([
            'customer' => '100010',
            'order_date' => $this->daysAgo(18),
            'delivery_date' => $this->daysAgo(11),
            'reference_no' => 'SO-REF-3010',
            'remarks' => 'Serial tracked equipment sale.',
            'items' => [
                ['material' => '300012', 'qty' => 6, 'unit_price' => 3200, 'vat' => 'exclusive'],
                ['material' => '300015', 'qty' => 40, 'unit_price' => 430, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->ship($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(11),
            'complete' => true,
            'remarks' => 'Serial numbers recorded on the delivery receipt.',
            'lines' => [
                '300012' => ['qty' => 6, 'serial_number' => 'SN-2450-0001..0006', 'remarks' => 'Warranty registered for the customer.'],
                '300015' => ['qty' => 40, 'batch_number' => 'BATCH-A7734'],
            ],
        ]);
    }

    private function cancelledAndRevertedOrders(): void
    {
        // 9 - cancelled before anything shipped.
        $order = $this->createOrder([
            'customer' => '100011',
            'order_date' => $this->daysAgo(17),
            'delivery_date' => $this->daysAgo(2),
            'reference_no' => 'SO-REF-3011',
            'remarks' => 'Cancelled: customer postponed the project.',
            'items' => [
                ['material' => '300005', 'qty' => 25, 'unit_price' => 530, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->orders->cancel($order);

        // 10 - shipped, then cancelled: the issue is cancelled with the order
        //      and the stock comes back in.
        $order = $this->createOrder([
            'customer' => '100012',
            'order_date' => $this->daysAgo(21),
            'delivery_date' => $this->daysAgo(13),
            'reference_no' => 'SO-REF-3012',
            'remarks' => 'Delivery refused on site and returned to the warehouse.',
            'items' => [
                ['material' => '300004', 'qty' => 10, 'unit_price' => 1650, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->ship($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(13),
            'complete' => true,
            'remarks' => 'Delivered, then refused by the site engineer.',
            'lines' => ['300004' => 10],
        ]);
        $this->orders->cancel($order);

        // 11 - posted, then pulled back to draft to re-price.
        $order = $this->createOrder([
            'customer' => '100009',
            'order_date' => $this->daysAgo(6),
            'delivery_date' => $this->daysAhead(8),
            'reference_no' => 'SO-REF-3009',
            'remarks' => 'Reverted to draft: customer asked for a revised quote.',
            'items' => [
                ['material' => '300003', 'qty' => 35, 'unit_price' => 870, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->orders->revert($order);

        // 12 - cancelled, then restored with its pending issue reopened.
        $order = $this->createOrder([
            'customer' => '100015',
            'order_date' => $this->daysAgo(13),
            'delivery_date' => $this->daysAhead(4),
            'reference_no' => 'SO-REF-3015',
            'remarks' => 'Cancelled in error, restored after the customer confirmed.',
            'items' => [
                ['material' => '300002', 'qty' => 80, 'unit_price' => 261, 'vat' => 'exclusive'],
            ],
        ]);
        $this->orders->post($order);
        $this->ship($order, [
            'location' => 'WH-MNL',
            'date' => $this->daysAgo(1),
            'remarks' => 'Picking list held while the order was on hold.',
            'lines' => ['300002' => 80],
        ]);
        $this->orders->cancel($order);
        $this->orders->revert($order);
    }

    // ── Builders ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $spec
     */
    private function createOrder(array $spec): SalesOrder
    {
        [$discountType, $discountAmount] = $spec['discount'] ?? [null, 0];

        return $this->orders->create(SalesOrderData::fromArray([
            'customer_id' => $this->customerId($spec['customer']),
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
                'unit_price' => $item['unit_price'],
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
     * Raise a goods issue against an order and optionally complete it.
     *
     * Each line is capped at the quantity the location can actually promise,
     * so a thin opening balance produces a smaller sample document instead of
     * failing the seed.
     *
     * @param  array<string, mixed>  $spec
     */
    private function ship(SalesOrder $order, array $spec): ?GoodsIssue
    {
        $order->refresh()->load('items');

        $locationId = $this->locationId($spec['location']);

        if ($locationId === null) {
            return null;
        }

        $items = [];

        foreach ($spec['lines'] as $materialCode => $line) {
            $orderItem = $order->items->firstWhere('material_id', $this->materialId($materialCode));

            if ($orderItem === null) {
                continue;
            }

            $line = is_array($line) ? $line : ['qty' => $line];

            $available = $this->inventory->availableQuantity($orderItem->material_id, $locationId);
            $quantity = Money::quantity(min($line['qty'], $available));

            if ($quantity <= 0) {
                $this->command?->warn(sprintf(
                    '%s: skipped %s on %s - no stock available at %s.',
                    static::class,
                    $materialCode,
                    $order->code,
                    $spec['location'],
                ));

                continue;
            }

            $items[] = [
                'sales_order_item_id' => $orderItem->id,
                'qty_to_ship' => $quantity,
                'serial_number' => $line['serial_number'] ?? null,
                'batch_number' => $line['batch_number'] ?? null,
                'remarks' => $line['remarks'] ?? null,
            ];
        }

        if ($items === []) {
            return null;
        }

        $issue = $this->issues->create($order, GoodsIssueData::fromArray([
            'sales_order_id' => $order->id,
            'location_id' => $locationId,
            'gi_date' => $spec['date'],
            'transaction_date' => $spec['date'],
            'remarks' => $spec['remarks'] ?? null,
            'items' => $items,
        ]));

        if ($spec['complete'] ?? false) {
            $issue = $this->issues->complete($issue);
        }

        return $issue;
    }
}
