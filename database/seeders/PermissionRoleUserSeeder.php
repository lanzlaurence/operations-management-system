<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionRoleUserSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'role-view',
            'role-create',
            'role-edit',
            'role-delete',
            'user-view',
            'user-create',
            'user-edit',
            'user-delete',
            'preference-view',
            'preference-edit',
            'brand-view',
            'brand-create',
            'brand-edit',
            'brand-delete',
            'category-view',
            'category-create',
            'category-edit',
            'category-delete',
            'uom-view',
            'uom-create',
            'uom-edit',
            'uom-delete',
            'location-view',
            'location-create',
            'location-edit',
            'location-delete',
            'material-view',
            'material-create',
            'material-edit',
            'material-delete',
            'vendor-view',
            'vendor-create',
            'vendor-edit',
            'vendor-delete',
            'customer-view',
            'customer-create',
            'customer-edit',
            'customer-delete',
            'charge-view',
            'charge-create',
            'charge-edit',
            'charge-delete',
            'currency-view',
            'currency-create',
            'currency-edit',
            'currency-delete',
            'inventory-view',
            'inventory-adjust',
            'inventory-delete',
            'purchase-order-view',
            'purchase-order-create',
            'purchase-order-edit',
            'purchase-order-delete',
            'purchase-order-post',
            'purchase-order-cancel',
            'purchase-order-revert',
            'goods-receipt-view',
            'goods-receipt-create',
            'goods-receipt-edit',
            'goods-receipt-delete',
            'goods-receipt-cancel',
            'goods-receipt-complete',
            'goods-receipt-revert',
            'sales-order-view',
            'sales-order-create',
            'sales-order-edit',
            'sales-order-delete',
            'sales-order-post',
            'sales-order-cancel',
            'sales-order-revert',
            'goods-issue-view',
            'goods-issue-create',
            'goods-issue-edit',
            'goods-issue-delete',
            'goods-issue-cancel',
            'goods-issue-complete',
            'goods-issue-revert',
            'activity-transaction-log',
            'activity-inventory-log',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create Admin role and assign all permissions
        $adminRole = Role::create(['name' => 'Admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Create Admin user
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('P@ssw0rd'),
            'force_password_change' => false,
            'is_active' => true,
        ])->assignRole('Admin');
    }
}
