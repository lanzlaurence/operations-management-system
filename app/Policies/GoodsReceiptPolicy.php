<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

/**
 * Who may act on a goods receipt.
 *
 * Permission questions only - the state rules (pending, completed,
 * cancelled) are enforced by GoodsReceiptService so the user gets a message
 * rather than a 403.
 */
class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('goods-receipt-view');
    }

    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can('goods-receipt-view');
    }

    public function create(User $user): bool
    {
        return $user->can('goods-receipt-create');
    }

    public function update(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can('goods-receipt-edit');
    }

    public function delete(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can('goods-receipt-delete');
    }

    public function complete(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can('goods-receipt-complete');
    }

    public function cancel(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can('goods-receipt-cancel');
    }

    public function revert(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can('goods-receipt-revert');
    }
}
