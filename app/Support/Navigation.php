<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Resolves `config/navigation.php` into the menu the current user may see.
 *
 * The config holds the shape (labels, routes, icons, permissions); this class
 * answers the two questions the layout actually asks: which entries are
 * visible, and which one is active. Groups whose children are all hidden drop
 * out on their own, and an entry pointing at a route that does not exist is
 * skipped rather than throwing.
 */
final class Navigation
{
    /**
     * The menu for a user, ready to render.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for(?Authenticatable $user): array
    {
        return self::filter(config('navigation.items', []), $user);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function filter(array $items, ?Authenticatable $user): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (isset($item['items'])) {
                $children = self::filter($item['items'], $user);

                if ($children === []) {
                    continue;
                }

                $item['items'] = $children;
                $item['is_active'] = collect($children)->contains(fn (array $child): bool => $child['is_active']);

                $visible[] = $item;

                continue;
            }

            if (! self::isVisible($item, $user)) {
                continue;
            }

            $item['url'] = self::url($item);
            $item['is_active'] = self::isActive($item);

            $visible[] = $item;
        }

        return $visible;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function isVisible(array $item, ?Authenticatable $user): bool
    {
        $permission = $item['permission'] ?? null;

        if ($permission !== null && ! ($user?->can($permission) ?? false)) {
            return false;
        }

        // A route that has not been defined yet (module not built) is skipped
        // rather than throwing while rendering the shell.
        return self::url($item) !== null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function url(array $item): ?string
    {
        if (isset($item['url'])) {
            return $item['url'];
        }

        $route = $item['route'] ?? null;

        if ($route === null) {
            return null;
        }

        return Route::has($route) ? route($route) : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function isActive(array $item): bool
    {
        $patterns = $item['active'] ?? [];

        if ($patterns === [] && isset($item['route'])) {
            $patterns = [$item['route']];
        }

        $current = Route::currentRouteName();

        if ($current === null) {
            return false;
        }

        foreach ((array) $patterns as $pattern) {
            if (Str::is($pattern, $current)) {
                return true;
            }
        }

        return false;
    }
}
