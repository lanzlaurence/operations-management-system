<?php

namespace App\Exceptions;

use App\Models\Location;
use App\Models\Material;
use App\Support\Money;

/**
 * Raised when a stock movement would push a location below zero.
 *
 * It is thrown from InventoryService while the inventory row is locked, which
 * makes it the last line of defence behind the form request validation: two
 * concurrent issues for the same material can both pass validation, but only
 * one of them can pass this check.
 */
class InsufficientStockException extends BusinessRuleException
{
    public static function for(
        Material $material,
        ?Location $location,
        float $available,
        float $required,
    ): self {
        $where = $location === null ? '' : " at {$location->name}";

        return new self(sprintf(
            'Insufficient stock for [%s] %s%s. Available: %s, Required: %s.',
            $material->code,
            $material->name,
            $where,
            Money::quantity($available) + 0,
            Money::quantity($required) + 0,
        ));
    }
}
