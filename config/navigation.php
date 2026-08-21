<?php

/*
|--------------------------------------------------------------------------
| Sidebar navigation
|--------------------------------------------------------------------------
|
| One declaration of the application menu, rendered by the Livewire layout.
|
| Each entry accepts:
|   label       - text shown in the sidebar
|   route       - route name (preferred) or url for a direct link
|   icon        - heroicon name, e.g. `cube` renders <x-heroicon-o-cube />
|   permission  - spatie permission required to see the entry
|   active      - route name patterns that light the entry up
|   items       - child entries; a group is hidden when every child is
|
| A group with no visible children disappears on its own, so permissions only
| ever need to be declared on the leaves.
|
| `migrated` marks the modules already running on Livewire. It is only used to
| flag progress in the sidebar during the migration and can be deleted, along
| with the badge in the layout, once every module is over.
|
*/

return [

    'items' => [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'home',
            'active' => ['dashboard'],
        ],

        [
            'label' => 'Transaction',
            'icon' => 'arrow-trending-up',
            'items' => [
                [
                    'label' => 'Purchase Orders',
                    'route' => 'purchase-orders.index',
                    'icon' => 'shopping-cart',
                    'permission' => 'purchase-order-view',
                    'active' => ['purchase-orders.*'],
                ],
                [
                    'label' => 'Goods Receipts',
                    'route' => 'goods-receipts.index',
                    'icon' => 'archive-box-arrow-down',
                    'permission' => 'goods-receipt-view',
                    'active' => ['goods-receipts.*'],
                ],
                [
                    'label' => 'Sales Orders',
                    'route' => 'sales-orders.index',
                    'icon' => 'shopping-bag',
                    'permission' => 'sales-order-view',
                    'active' => ['sales-orders.*'],
                ],
                [
                    'label' => 'Goods Issues',
                    'route' => 'goods-issues.index',
                    'icon' => 'truck',
                    'permission' => 'goods-issue-view',
                    'active' => ['goods-issues.*'],
                ],
                [
                    'label' => 'Inventory',
                    'route' => 'inventories.index',
                    'icon' => 'building-storefront',
                    'permission' => 'inventory-view',
                    'active' => ['inventories.*'],
                    'migrated' => true,
                ],
            ],
        ],

        [
            'label' => 'Activity',
            'icon' => 'chart-bar',
            'items' => [
                [
                    'label' => 'Transaction Log',
                    'route' => 'activity.transaction-log',
                    'icon' => 'document-text',
                    'permission' => 'activity-transaction-log',
                    'active' => ['activity.transaction-log'],
                    'migrated' => true,
                ],
                [
                    'label' => 'Inventory Log',
                    'route' => 'activity.inventory-log',
                    'icon' => 'clipboard-document-list',
                    'permission' => 'activity-inventory-log',
                    'active' => ['activity.inventory-log'],
                    'migrated' => true,
                ],
            ],
        ],

        [
            'label' => 'Master',
            'icon' => 'cube',
            'items' => [
                [
                    'label' => 'Materials',
                    'route' => 'materials.index',
                    'icon' => 'cube-transparent',
                    'permission' => 'material-view',
                    'active' => ['materials.*'],
                    'migrated' => true,
                ],
                [
                    'label' => 'Vendors',
                    'route' => 'vendors.index',
                    'icon' => 'building-office-2',
                    'permission' => 'vendor-view',
                    'active' => ['vendors.*'],
                    'migrated' => true,
                ],
                [
                    'label' => 'Customers',
                    'route' => 'customers.index',
                    'icon' => 'user-circle',
                    'permission' => 'customer-view',
                    'active' => ['customers.*'],
                    'migrated' => true,
                ],
            ],
        ],

        [
            'label' => 'Configuration',
            'icon' => 'cog-6-tooth',
            'items' => [
                [
                    'label' => 'Brands',
                    'route' => 'brands.index',
                    'icon' => 'tag',
                    'permission' => 'brand-view',
                    'active' => ['brands.*'],
                    'migrated' => true,
                ],
                [
                    'label' => 'Categories',
                    'route' => 'categories.index',
                    'icon' => 'folder',
                    'permission' => 'category-view',
                    'active' => ['categories.*'],
                    'migrated' => true,
                ],
                [
                    'label' => 'UOM',
                    'route' => 'uoms.index',
                    'icon' => 'scale',
                    'permission' => 'uom-view',
                    'active' => ['uoms.*'],
                    'migrated' => true,
                ],
                [
                    'label' => 'Locations',
                    'route' => 'locations.index',
                    'icon' => 'map-pin',
                    'permission' => 'location-view',
                    'active' => ['locations.*'],
                    'migrated' => true,
                ],
                [
                    'label' => 'Charges',
                    'route' => 'charges.index',
                    'icon' => 'credit-card',
                    'permission' => 'charge-view',
                    'active' => ['charges.*'],
                    'migrated' => true,
                ],
                [
                    'label' => 'Currencies',
                    'route' => 'currencies.index',
                    'icon' => 'banknotes',
                    'permission' => 'currency-view',
                    'active' => ['currencies.*'],
                    'migrated' => true,
                ],
            ],
        ],

        [
            'label' => 'System',
            'icon' => 'adjustments-horizontal',
            'items' => [
                [
                    'label' => 'Users',
                    'route' => 'users.index',
                    'icon' => 'users',
                    'permission' => 'user-view',
                    'active' => ['users.*'],
                    'migrated' => true,
                ],
                [
                    'label' => 'Roles',
                    'route' => 'roles.index',
                    'icon' => 'shield-check',
                    'permission' => 'role-view',
                    'active' => ['roles.*'],
                    'migrated' => true,
                ],
                [
                    'label' => 'Preferences',
                    'route' => 'preferences.index',
                    'icon' => 'wrench-screwdriver',
                    'permission' => 'preference-view',
                    'active' => ['preferences.*'],
                    'migrated' => true,
                ],
            ],
        ],
    ],

];
