<?php

use App\Enums\DiscountType;
use App\Enums\VatType;
use App\Services\Support\LineCalculator;

/**
 * The line calculator is the one place document pricing is decided, so its
 * rules are pinned down here rather than rediscovered from the screens.
 */
beforeEach(function (): void {
    $this->calculator = new LineCalculator;
});

it('adds VAT on top of an exclusive line', function (): void {
    $line = $this->calculator->calculate(
        quantity: 10,
        unitAmount: 100,
        isVatable: true,
        vatType: VatType::Exclusive,
        vatRate: 12,
    );

    expect($line->netPrice)->toBe(1000.0)
        ->and($line->vatPrice)->toBe(120.0)
        ->and($line->grossPrice)->toBe(1120.0);
});

it('extracts VAT out of an inclusive line so the gross stays the entered amount', function (): void {
    $line = $this->calculator->calculate(
        quantity: 10,
        unitAmount: 112,
        isVatable: true,
        vatType: VatType::Inclusive,
        vatRate: 12,
    );

    expect($line->grossPrice)->toBe(1120.0)
        ->and($line->netPrice)->toBe(1000.0)
        ->and($line->vatPrice)->toBe(120.0);
});

it('keeps net, VAT and gross consistent for a non vatable line', function (): void {
    $line = $this->calculator->calculate(quantity: 3, unitAmount: 250);

    expect($line->netPrice)->toBe(750.0)
        ->and($line->vatPrice)->toBe(0.0)
        ->and($line->grossPrice)->toBe(750.0)
        ->and($line->vatType)->toBeNull();
});

it('applies a fixed discount to the unit amount before multiplying', function (): void {
    $line = $this->calculator->calculate(
        quantity: 4,
        unitAmount: 100,
        discountType: DiscountType::Fixed,
        discountAmount: 25,
    );

    expect($line->unitAmountAfterDiscount)->toBe(75.0)
        ->and($line->lineDiscountTotal)->toBe(100.0)
        ->and($line->netPrice)->toBe(300.0);
});

it('applies a percentage discount to the unit amount', function (): void {
    $line = $this->calculator->calculate(
        quantity: 2,
        unitAmount: 200,
        discountType: DiscountType::Percentage,
        discountAmount: 10,
        isVatable: true,
        vatType: VatType::Exclusive,
        vatRate: 12,
    );

    expect($line->unitAmountAfterDiscount)->toBe(180.0)
        ->and($line->netPrice)->toBe(360.0)
        ->and($line->vatPrice)->toBe(43.2)
        ->and($line->grossPrice)->toBe(403.2);
});

it('never lets a discount push a line below zero', function (): void {
    $line = $this->calculator->calculate(
        quantity: 5,
        unitAmount: 50,
        discountType: DiscountType::Fixed,
        discountAmount: 120,
    );

    expect($line->unitAmountAfterDiscount)->toBe(0.0)
        ->and($line->netPrice)->toBe(0.0)
        ->and($line->grossPrice)->toBe(0.0);
});

it('defaults a vatable line without a type to exclusive at 12 percent', function (): void {
    $line = $this->calculator->calculate(quantity: 1, unitAmount: 100, isVatable: true);

    expect($line->vatType)->toBe(VatType::Exclusive)
        ->and($line->vatRate)->toBe(12.0)
        ->and($line->grossPrice)->toBe(112.0);
});
