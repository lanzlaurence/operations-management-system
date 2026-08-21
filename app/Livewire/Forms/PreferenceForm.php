<?php

namespace App\Livewire\Forms;

use App\Models\Currency;
use App\Models\Preference;
use App\Support\Branding;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use Livewire\WithFileUploads;

/**
 * Application preferences.
 *
 * These are key/value rows rather than a table of columns, so the form maps
 * each field onto `Preference::set()` and lets the model's own cache
 * invalidation take care of the rest.
 */
class PreferenceForm extends Form
{
    use WithFileUploads;

    public string $app_name = '';

    public string $decimal_places = '2';

    public string $color_theme = 'zinc';

    public string $timezone = 'Asia/Manila';

    public string $currency = 'PHP';

    public string $date_format = 'MM/DD/YYYY';

    public string $time_format = '12h';

    /** A newly picked logo, still in temporary storage. */
    public ?TemporaryUploadedFile $app_logo = null;

    /** Accents the stylesheet defines; the layout reads the saved one back. */
    public const THEMES = Branding::ACCENTS;

    /** Date formats offered, with a sample of each. */
    public const DATE_FORMATS = [
        'MM/DD/YYYY' => '08/20/2026',
        'DD/MM/YYYY' => '20/08/2026',
        'YYYY-MM-DD' => '2026-08-20',
        'MMM DD, YYYY' => 'Aug 20, 2026',
        'MMMM DD, YYYY' => 'August 20, 2026',
        'DD MMM YYYY' => '20 Aug 2026',
    ];

    public function loadCurrent(): void
    {
        $this->app_name = (string) Preference::get('app_name', config('app.name'));
        $this->decimal_places = (string) Preference::get('decimal_places', '2');
        $this->color_theme = (string) Preference::get('color_theme', 'zinc');
        $this->timezone = (string) Preference::get('timezone', 'Asia/Manila');
        $this->currency = (string) Preference::get('currency', 'PHP');
        $this->date_format = (string) Preference::get('date_format', 'MM/DD/YYYY');
        $this->time_format = (string) Preference::get('time_format', '12h');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:255'],
            'app_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:5120'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'color_theme' => ['required', 'string', Rule::in(self::THEMES)],
            'timezone' => ['required', 'string', 'timezone:all'],
            'currency' => ['required', 'string', Rule::exists('currencies', 'code')->where('is_active', true)],
            'date_format' => ['required', 'string', Rule::in(array_keys(self::DATE_FORMATS))],
            'time_format' => ['required', 'string', Rule::in(['12h', '24h'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'app_name.required' => 'The application needs a name; it appears in the sidebar and page titles.',
            'app_logo.image' => 'The logo must be an image file.',
            'app_logo.max' => 'The logo must be 5 MB or smaller.',
            'currency.exists' => 'Choose an active currency.',
            'timezone.timezone' => 'Choose a valid timezone.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'app_name' => 'application name',
            'app_logo' => 'logo',
            'decimal_places' => 'decimal places',
            'color_theme' => 'colour theme',
            'date_format' => 'date format',
            'time_format' => 'time format',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['app_name'] = trim((string) ($attributes['app_name'] ?? ''));

        return $attributes;
    }

    /**
     * Active currencies for the select.
     *
     * @return Collection<int, Currency>
     */
    public function currencies(): Collection
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);
    }
}
