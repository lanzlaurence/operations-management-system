<?php

namespace App\Http\Requests;

use App\Models\GoodsIssue;
use Illuminate\Validation\Validator;

/**
 * Validates an edit to a pending goods issue.
 *
 * Reuses the store rules, resolves the sales order from the issue itself and
 * excludes this issue from both the outstanding quantity and the stock
 * reservation, so editing a line does not fight with its own reservation.
 */
class UpdateGoodsIssueRequest extends StoreGoodsIssueRequest
{
    public function authorize(): bool
    {
        $issue = $this->issue();

        return $issue !== null && ($this->user()?->can('update', $issue) ?? false);
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'sales_order_id' => $this->issue()?->sales_order_id,
        ]);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateAgainstOrder($validator),
        ];
    }

    protected function ignoredIssueId(): ?int
    {
        return $this->issue()?->id;
    }

    private function issue(): ?GoodsIssue
    {
        $issue = $this->route('goods_issue');

        return $issue instanceof GoodsIssue ? $issue : null;
    }
}
