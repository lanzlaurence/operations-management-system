<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for every backed enum in the application.
 *
 * Keeping them in one trait means each enum exposes the same small API
 * (`values()`, `options()`, `rule()`, `parse()`) that the form requests,
 * services and Inertia payloads rely on.
 */
trait EnumHelpers
{
    /**
     * All raw values of the enum, ready for validation rules or `whereIn()`.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Value => label map, used to feed select inputs on the frontend.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Comma separated list of values for an `in:` validation rule.
     */
    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * Resolve a case from a raw value, falling back to `$default` when the
     * value is null or unknown instead of throwing.
     */
    public static function parse(mixed $value, ?self $default = null): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return self::tryFrom((string) $value) ?? $default;
    }

    /**
     * Human readable label derived from the case name: `PartiallyReceived`
     * becomes "Partially Received". Enums may override this for nicer wording.
     */
    public function label(): string
    {
        return str($this->name)->headline()->value();
    }
}
