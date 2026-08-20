<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

/**
 * Who may act on a sales order.
 *
 * The policy answers the permission question only ("is this user allowed to
 * cancel sales orders at all"). Whether *this particular* order can be
 * cancelled right now is a domain rule and lives in SalesOrderService, so
 * a user with the right permission gets an explanatory message instead of a
 * bare 403 when the document is in the wrong state.
 */
class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales-order-view');
    }

    public function view(User $user, SalesOrder $order): bool
    {
        return $user->can('sales-order-view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales-order-create');
    }

    public function update(User $user, SalesOrder $order): bool
    {
        return $user->can('sales-order-edit');
    }

    public function delete(User $user, SalesOrder $order): bool
    {
        return $user->can('sales-order-delete');
    }

    public function post(User $user, SalesOrder $order): bool
    {
        return $user->can('sales-order-post');
    }

    public function cancel(User $user, SalesOrder $order): bool
    {
        return $user->can('sales-order-cancel');
    }

    public function revert(User $user, SalesOrder $order): bool
    {
        return $user->can('sales-order-revert');
    }

    /** Raising a goods issue is a shipping permission, not a selling one. */
    public function ship(User $user, SalesOrder $order): bool
    {
        return $user->can('goods-issue-create');
    }
}
