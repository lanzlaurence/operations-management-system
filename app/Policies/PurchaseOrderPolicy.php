<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Who may act on a purchase order.
 *
 * The policy answers the permission question only ("is this user allowed to
 * cancel purchase orders at all"). Whether *this particular* order can be
 * cancelled right now is a domain rule and lives in PurchaseOrderService, so
 * a user with the right permission gets an explanatory message instead of a
 * bare 403 when the document is in the wrong state.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase-order-view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-order-view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-order-create');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-order-edit');
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-order-delete');
    }

    public function post(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-order-post');
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-order-cancel');
    }

    public function revert(User $user, PurchaseOrder $order): bool
    {
        return $user->can('purchase-order-revert');
    }

    /** Raising a goods receipt is a receiving permission, not a buying one. */
    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $user->can('goods-receipt-create');
    }
}
