<?php

namespace App\Data;

use App\Enums\ChargeType;
use App\Enums\ChargeValueType;
use App\Models\Charge;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * A charge as it is attached to a document.
 *
 * The name, type, value type and value are snapshotted from the master charge
 * so that later edits to the master record never rewrite history on documents
 * that were already posted.
 */
final readonly class DocumentChargeData
{
    public function __construct(
        public int $chargeId,
        public string $name,
        public ChargeType $type,
        public ChargeValueType $valueType,
        public float $value,
    ) {}

    public static function fromCharge(Charge $charge): self
    {
        return new self(
            chargeId: $charge->id,
            name: $charge->name,
            type: $charge->type,
            valueType: $charge->value_type,
            value: Money::round($charge->value),
        );
    }

    /**
     * Build the charge snapshots for a request payload, resolving every
     * `charge_id` in a single query and silently dropping unknown ids
     * (the form request has already validated their existence).
     *
     * @param  array<int, array{charge_id?: int|string}>|null  $rows
     * @return array<int, self>
     */
    public static function collectionFromRows(?array $rows): array
    {
        $ids = collect($rows ?? [])
            ->pluck('charge_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id);

        if ($ids->isEmpty()) {
            return [];
        }

        /** @var Collection<int, Charge> $charges */
        $charges = Charge::query()->whereKey($ids->unique())->get()->keyBy('id');

        return $ids
            ->map(fn (int $id): ?Charge => $charges->get($id))
            ->filter()
            ->map(fn (Charge $charge): self => self::fromCharge($charge))
            ->values()
            ->all();
    }

    /**
     * Columns for `purchase_order_charges` / `sales_order_charges`.
     *
     * @return array<string, mixed>
     */
    public function toColumns(float $computedAmount): array
    {
        return [
            'charge_id' => $this->chargeId,
            'name' => $this->name,
            'type' => $this->type->value,
            'value_type' => $this->valueType->value,
            'value' => $this->value,
            'computed_amount' => Money::round($computedAmount),
        ];
    }
}
