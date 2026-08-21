<?php

namespace App\Policies;

use App\Models\GoodsIssue;
use App\Models\User;

/**
 * Who may act on a goods issue.
 *
 * Permission questions only - the state rules (pending, completed,
 * cancelled) are enforced by GoodsIssueService so the user gets a message
 * rather than a 403.
 */
class GoodsIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('goods-issue-view');
    }

    public function view(User $user, GoodsIssue $issue): bool
    {
        return $user->can('goods-issue-view');
    }

    public function create(User $user): bool
    {
        return $user->can('goods-issue-create');
    }

    public function update(User $user, GoodsIssue $issue): bool
    {
        return $user->can('goods-issue-edit');
    }

    public function delete(User $user, GoodsIssue $issue): bool
    {
        return $user->can('goods-issue-delete');
    }

    public function complete(User $user, GoodsIssue $issue): bool
    {
        return $user->can('goods-issue-complete');
    }

    public function cancel(User $user, GoodsIssue $issue): bool
    {
        return $user->can('goods-issue-cancel');
    }

    public function revert(User $user, GoodsIssue $issue): bool
    {
        return $user->can('goods-issue-revert');
    }
}
