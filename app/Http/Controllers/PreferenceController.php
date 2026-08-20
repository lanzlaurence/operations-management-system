<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferenceRequest;
use App\Models\Currency;
use App\Models\Preference;
use App\Traits\HandlesFileUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PreferenceController extends Controller implements HasMiddleware
{
    use HandlesFileUpload;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:preference-view', only: ['index']),
            new Middleware('permission:preference-edit', only: ['update']),
        ];
    }

    public function index(): Response
    {
        $formData = [
            'app_name' => Preference::get('app_name', 'Operations Management System'),
            'app_logo_url' => $this->getLogoUrl(),
            'decimal_places' => Preference::get('decimal_places', '2'),
            'color_theme' => Preference::get('color_theme', 'blue'),
            'timezone' => Preference::get('timezone', 'Asia/Manila'),
            'currency' => Preference::get('currency', 'PHP'),
            'date_format' => Preference::get('date_format', 'MM/DD/YYYY'),
            'time_format' => Preference::get('time_format', '12h'),
        ];

        $currencies = Currency::where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);

        return Inertia::render('preference/index', [
            'formData' => $formData,
            'currencies' => $currencies,
        ]);
    }

    public function update(UpdatePreferenceRequest $request): RedirectResponse
    {
        Preference::set('app_name', $request->app_name);
        Preference::set('decimal_places', $request->decimal_places, 'number');
        Preference::set('color_theme', $request->color_theme);
        Preference::set('timezone', $request->timezone);
        Preference::set('currency', $request->currency);
        Preference::set('date_format', $request->date_format);
        Preference::set('time_format', $request->time_format);

        if ($request->hasFile('app_logo')) {
            $oldLogo = Preference::get('app_logo');

            if ($oldLogo && $oldLogo !== 'default-logo.jpg') {
                $this->deleteFile($oldLogo, 'public');
            }

            $logoPath = $this->uploadFile(
                $request->file('app_logo'),
                'logos',
                'public'
            );

            Preference::set('app_logo', $logoPath, 'image');
        }

        return redirect()->route('preferences.index')
            ->with('success', 'Preferences updated successfully');
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
