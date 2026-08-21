<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Groups the flat permission list into something a person can reason about.
 *
 * Permissions are stored as `subject-ability` (`purchase-order-post`), which is
 * convenient for `can()` checks and unreadable as a checkbox list of eighty
 * entries. This turns them into subjects with their abilities, in the order the
 * modules appear in the application.
 */
final class PermissionCatalog
{
    /**
     * Abilities in the order they should read, most passive first.
     *
     * @var array<int, string>
     */
    private const ABILITY_ORDER = [
        'view', 'create', 'edit', 'delete',
        'post', 'complete', 'cancel', 'revert', 'adjust',
    ];

    /**
     * Subjects grouped by the part of the application they belong to, so the
     * matrix reads like the sidebar rather than like the database.
     *
     * @var array<string, array<int, string>>
     */
    private const GROUPS = [
        'Purchasing' => ['purchase-order', 'goods-receipt'],
        'Sales' => ['sales-order', 'goods-issue'],
        'Inventory' => ['inventory'],
        'Master data' => ['material', 'vendor', 'customer'],
        'Configuration' => ['brand', 'category', 'uom', 'location', 'charge', 'currency'],
        'Activity' => ['activity'],
        'System' => ['user', 'role', 'preference'],
    ];

    /**
     * The catalogue: group => subject => [ability => permission name].
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function grouped(): array
    {
        $permissions = Permission::query()->orderBy('name')->pluck('name');

        $bySubject = $permissions
            ->groupBy(fn (string $permission): string => self::subjectOf($permission))
            ->map(fn (Collection $items): array => $items
                ->mapWithKeys(fn (string $permission): array => [self::abilityOf($permission) => $permission])
                ->sortBy(fn (string $permission, string $ability): int => self::abilityRank($ability))
                ->all());

        $catalogue = [];

        foreach (self::GROUPS as $group => $subjects) {
            foreach ($subjects as $subject) {
                if ($bySubject->has($subject)) {
                    $catalogue[$group][$subject] = $bySubject->get($subject);
                }
            }
        }

        // Anything the map above does not mention still has to be reachable.
        $mapped = collect(self::GROUPS)->flatten()->all();

        foreach ($bySubject as $subject => $abilities) {
            if (! in_array($subject, $mapped, true)) {
                $catalogue['Other'][$subject] = $abilities;
            }
        }

        return $catalogue;
    }

    /**
     * Every permission name, for the "select all" control.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return Permission::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * Human label for a subject: `purchase-order` becomes "Purchase Order".
     */
    public static function subjectLabel(string $subject): string
    {
        return str($subject)->replace('-', ' ')->title()->value();
    }

    /**
     * Human label for an ability: `activity-transaction-log` yields
     * "Transaction Log" rather than a bare verb.
     */
    public static function abilityLabel(string $ability): string
    {
        return str($ability)->replace('-', ' ')->title()->value();
    }

    /**
     * The subject a permission belongs to, i.e. everything before the ability.
     */
    private static function subjectOf(string $permission): string
    {
        $segments = explode('-', $permission);

        // `activity-transaction-log` and `activity-inventory-log` are one
        // subject with multi-word abilities.
        if ($segments[0] === 'activity') {
            return 'activity';
        }

        array_pop($segments);

        return implode('-', $segments) ?: $permission;
    }

    private static function abilityOf(string $permission): string
    {
        $subject = self::subjectOf($permission);

        return trim(str($permission)->after($subject)->value(), '-');
    }

    private static function abilityRank(string $ability): int
    {
        $index = array_search($ability, self::ABILITY_ORDER, true);

        return $index === false ? count(self::ABILITY_ORDER) : $index;
    }
}
