<?php

namespace App\Support;

use App\Models\Preference;
use Illuminate\Support\Facades\Storage;

/**
 * Application name and logo, resolved from the preferences table.
 *
 * Every layout reads the name, logo and accent from here, with the packaged
 * default as the fallback whenever the stored logo file is missing.
 */
final class Branding
{
    private const DEFAULT_LOGO = 'default-logo.jpg';

    private const DEFAULT_ACCENT = 'zinc';

    /**
     * Accents the stylesheet defines. Preferences validates against this list,
     * so a stored value outside it can only come from a hand-edited row.
     *
     * @var array<int, string>
     */
    public const ACCENTS = ['zinc', 'blue', 'violet', 'green', 'rose', 'orange'];

    public static function appName(): string
    {
        return (string) Preference::get('app_name', config('app.name', 'Operations Management System'));
    }

    public static function logoUrl(): string
    {
        $logo = (string) Preference::get('app_logo', self::DEFAULT_LOGO);

        if ($logo === self::DEFAULT_LOGO || $logo === '') {
            return asset(self::DEFAULT_LOGO);
        }

        return Storage::disk('public')->exists($logo)
            ? Storage::disk('public')->url($logo)
            : asset(self::DEFAULT_LOGO);
    }

    /**
     * The accent applied to `<html data-accent>`, which the stylesheet turns
     * into DaisyUI's primary colour. Anything unrecognised falls back rather
     * than emitting an attribute no rule matches.
     */
    public static function accent(): string
    {
        $accent = (string) Preference::get('color_theme', self::DEFAULT_ACCENT);

        return in_array($accent, self::ACCENTS, true) ? $accent : self::DEFAULT_ACCENT;
    }
}
