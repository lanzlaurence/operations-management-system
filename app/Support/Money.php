<?php

namespace App\Support;

/**
 * Money and quantity rounding helpers.
 *
 * All monetary columns are `decimal(15,2)` and all quantity columns are
 * `decimal(15,6)`, so every computed value passes through here before it is
 * persisted. Doing the rounding in one place keeps the line totals, the
 * document totals and the seeders from drifting apart by fractions of a cent.
 */
final class Money
{
    /** Decimal places used by monetary columns. */
    public const SCALE = 2;

    /** Decimal places used by quantity columns. */
    public const QTY_SCALE = 6;

    /**
     * Round a monetary amount, treating -0.0 as 0.0.
     */
    public static function round(float|int|string|null $amount): float
    {
        $value = round((float) $amount, self::SCALE);

        return $value === -0.0 ? 0.0 : $value;
    }

    /**
     * Round a quantity to the precision of the quantity columns.
     */
    public static function quantity(float|int|string|null $quantity): float
    {
        $value = round((float) $quantity, self::QTY_SCALE);

        return $value === -0.0 ? 0.0 : $value;
    }

    /**
     * Sum a list of amounts with a single rounding pass at the end.
     *
     * @param  iterable<float|int|string|null>  $amounts
     */
    public static function sum(iterable $amounts): float
    {
        $total = 0.0;

        foreach ($amounts as $amount) {
            $total += (float) $amount;
        }

        return self::round($total);
    }

    /**
     * Compare two amounts at monetary precision.
     */
    public static function equals(float|int|string|null $left, float|int|string|null $right): bool
    {
        return abs(self::round($left) - self::round($right)) < 0.005;
    }

    /**
     * Compare two quantities at quantity precision.
     */
    public static function quantityEquals(float|int|string|null $left, float|int|string|null $right): bool
    {
        return abs(self::quantity($left) - self::quantity($right)) < 0.0000005;
    }

    /**
     * Never let a computed amount fall below zero.
     */
    public static function positive(float|int|string|null $amount): float
    {
        return max(0.0, self::round($amount));
    }
}
