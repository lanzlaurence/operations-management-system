<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Services\InventoryService;
use Database\Seeders\Concerns\SeedsThroughServices;
use Illuminate\Database\Seeder;

/**
 * Opening stock, a few count corrections and two inter-location transfers.
 *
 * Written through InventoryService, so each balance comes with the matching
 * movement log (initial / adjustment / transfer_out / transfer_in) and the
 * ledger reconciles against `inventories.quantity` from the very first row.
 */
class InventorySeeder extends Seeder
{
    use SeedsThroughServices;

    public function __construct(private readonly InventoryService $inventory) {}

    public function run(): void
    {
        $this->asAdministrator(function (): void {
            $this->openingStock();
            $this->countCorrections();
            $this->transfers();
        });
    }

    /**
     * Opening balances per location: material code, location code, quantity.
     */
    private function openingStock(): void
    {
        $balances = [
            // Manila warehouse - the main stocking point
            ['300001', 'WH-MNL', 250],
            ['300002', 'WH-MNL', 500],
            ['300003', 'WH-MNL', 120],
            ['300004', 'WH-MNL', 80],
            ['300005', 'WH-MNL', 200],
            ['300006', 'WH-MNL', 1000],
            ['300007', 'WH-MNL', 300],
            ['300008', 'WH-MNL', 30],

            // Cebu warehouse
            ['300001', 'WH-CEB', 100],
            ['300002', 'WH-CEB', 200],
            ['300003', 'WH-CEB', 50],
            ['300005', 'WH-CEB', 80],

            // Davao warehouse
            ['300001', 'WH-DAV', 75],
            ['300002', 'WH-DAV', 150],
            ['300006', 'WH-DAV', 400],
            ['300007', 'WH-DAV', 120],

            // Retail stores
            ['300004', 'ST-BGC', 20],
            ['300006', 'ST-BGC', 150],
            ['300009', 'ST-BGC', 30],
            ['300010', 'ST-BGC', 15],
            ['300004', 'ST-MAK', 15],
            ['300006', 'ST-MAK', 200],
            ['300010', 'ST-MAK', 10],

            // Distribution centre and hub
            ['300001', 'DC-NTH', 180],
            ['300002', 'DC-NTH', 300],
            ['300011', 'DC-NTH', 600],
            ['300012', 'DC-NTH', 25],
            ['300013', 'HUB-CLK', 100],
            ['300014', 'HUB-CLK', 200],
        ];

        foreach ($balances as [$materialCode, $locationCode, $quantity]) {
            $materialId = $this->materialId($materialCode);
            $locationId = $this->locationId($locationCode);

            if ($materialId === null || $locationId === null) {
                continue;
            }

            $this->inventory->initialise(
                materialId: $materialId,
                locationId: $locationId,
                quantity: $quantity,
                remarks: 'Opening stock',
            );
        }
    }

    /**
     * Physical count corrections, so the ledger shows both an increase and a
     * decrease of the adjustment type.
     */
    private function countCorrections(): void
    {
        $corrections = [
            ['300001', 'WH-MNL', 270, 'Stock count correction: 20 rods found in the yard'],
            ['300002', 'WH-MNL', 480, 'Damaged goods written off after the count'],
            ['300004', 'WH-MNL', 90, 'Physical count adjustment'],
        ];

        foreach ($corrections as [$materialCode, $locationCode, $quantity, $remarks]) {
            $inventory = $this->findInventory($materialCode, $locationCode);

            if ($inventory === null) {
                continue;
            }

            $this->inventory->adjustTo($inventory, $quantity, $remarks);
        }
    }

    /**
     * Stock moved between locations, written as an out/in pair.
     */
    private function transfers(): void
    {
        $transfers = [
            ['300001', 'WH-MNL', 'DC-NTH', 30, 'Transfer to the north DC for distribution'],
            ['300006', 'WH-MNL', 'ST-BGC', 50, 'Restocking the BGC store'],
        ];

        foreach ($transfers as [$materialCode, $from, $to, $quantity, $remarks]) {
            $inventory = $this->findInventory($materialCode, $from);
            $toLocationId = $this->locationId($to);

            if ($inventory === null || $toLocationId === null) {
                continue;
            }

            $this->inventory->transfer($inventory, $toLocationId, $quantity, $remarks);
        }
    }

    private function findInventory(string $materialCode, string $locationCode): ?Inventory
    {
        $materialId = $this->materialId($materialCode);
        $locationId = $this->locationId($locationCode);

        if ($materialId === null || $locationId === null) {
            return null;
        }

        return Inventory::query()
            ->where('material_id', $materialId)
            ->where('location_id', $locationId)
            ->first();
    }
}
