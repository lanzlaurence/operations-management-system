<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;

/**
 * Who may look at and adjust stock.
 *
 * Stock is never edited directly: `adjust` covers the manual adjustment
 * screen (initial stock, corrections, transfers), while everything else moves
 * through goods receipts and goods issues.
 */
class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory-view');
    }

    public function view(User $user, Inventory $inventory): bool
    {
        return $user->can('inventory-view');
    }

    public function adjust(User $user): bool
    {
        return $user->can('inventory-adjust');
    }

    public function delete(User $user, Inventory $inventory): bool
    {
        return $user->can('inventory-delete');
    }
}
