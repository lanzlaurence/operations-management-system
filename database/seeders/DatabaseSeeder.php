<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionRoleUserSeeder::class,
            PreferenceSeeder::class,
            BrandSeeder::class,
            CategorySeeder::class,
            UomSeeder::class,
            LocationSeeder::class,
            MaterialSeeder::class,
            VendorSeeder::class,
            CustomerSeeder::class,
            ChargeSeeder::class,
            CurrencySeeder::class,
            InventorySeeder::class,
            PurchaseOrderSeeder::class,
            SalesOrderSeeder::class,
        ]);
    }
}
