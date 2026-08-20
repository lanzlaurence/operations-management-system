<?php

namespace App\Http\Middleware;

use App\Models\Preference;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? $request->user()->load('roles.permissions') : null,
            ],
            'preferences' => [
                'app_name' => Preference::get('app_name', 'Operations Management System'),
                'app_logo' => $this->getLogoUrl(),
                'decimal_places' => (int) Preference::get('decimal_places', '2'),
                'color_theme' => Preference::get('color_theme', 'zinc'),
                'timezone' => Preference::get('timezone', 'Asia/Manila'),
                'currency' => Preference::get('currency', 'PHP'),
                'date_format'   => Preference::get('date_format', 'MM/DD/YYYY'),
                'time_format'   => Preference::get('time_format', '12h'),
            ],
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(['code', 'name', 'symbol']),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    private function getLogoUrl(): string
    {
        $logo = Preference::get('app_logo', 'default-logo.jpg');

        if ($logo === 'default-logo.jpg') {
            return asset('default-logo.jpg');
        }

        return Storage::disk('public')->url($logo);
    }
}
