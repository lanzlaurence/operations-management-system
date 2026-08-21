<?php

namespace App\Livewire\Preferences;

use App\Livewire\Forms\PreferenceForm;
use App\Models\Preference;
use App\Support\Branding;
use App\Traits\HandlesFileUpload;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Application preferences: name, logo, and the display settings every screen
 * formats against.
 *
 * The logo is the only file upload in the application. The previous one is
 * removed after the new one is stored, and never the packaged default.
 */
#[Layout('components.layouts.app')]
#[Title('Preferences')]
class Edit extends Component
{
    use HandlesFileUpload;
    use WithFileUploads;

    public PreferenceForm $form;

    /** The packaged logo, which must never be deleted. */
    private const DEFAULT_LOGO = 'default-logo.jpg';

    public function mount(): void
    {
        $this->form->loadCurrent();
    }

    public function save(): void
    {
        $data = $this->form->validate();

        Preference::set('app_name', $data['app_name']);
        Preference::set('decimal_places', (string) $data['decimal_places'], 'number');
        Preference::set('color_theme', $data['color_theme']);
        Preference::set('timezone', $data['timezone']);
        Preference::set('currency', $data['currency']);
        Preference::set('date_format', $data['date_format']);
        Preference::set('time_format', $data['time_format']);

        if ($this->form->app_logo !== null) {
            $this->replaceLogo();
        }

        session()->flash('success', 'Preferences updated.');

        $this->redirectRoute('preferences.index', navigate: true);
    }

    /**
     * Drop a newly picked logo before saving.
     */
    public function removeSelectedLogo(): void
    {
        $this->form->app_logo = null;
        $this->resetValidation('form.app_logo');
    }

    /**
     * Go back to the packaged logo and delete the uploaded one.
     */
    public function resetLogo(): void
    {
        $current = (string) Preference::get('app_logo', self::DEFAULT_LOGO);

        if ($current !== self::DEFAULT_LOGO && $current !== '') {
            $this->deleteFile($current, 'public');
        }

        Preference::set('app_logo', self::DEFAULT_LOGO, 'image');

        $this->form->app_logo = null;

        $this->dispatch('toast', type: 'success', message: 'Logo reset to the default.');
    }

    public function logoUrl(): string
    {
        return Branding::logoUrl();
    }

    public function usingDefaultLogo(): bool
    {
        return (string) Preference::get('app_logo', self::DEFAULT_LOGO) === self::DEFAULT_LOGO;
    }

    public function render(): View
    {
        return view('livewire.preferences.edit', [
            'currencies' => $this->form->currencies(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'themes' => PreferenceForm::THEMES,
            'dateFormats' => PreferenceForm::DATE_FORMATS,
        ]);
    }

    /**
     * Store the new logo, then remove the one it replaced.
     */
    private function replaceLogo(): void
    {
        $previous = (string) Preference::get('app_logo', self::DEFAULT_LOGO);

        $path = $this->uploadFile($this->form->app_logo, 'logos', 'public');

        Preference::set('app_logo', $path, 'image');

        if ($previous !== self::DEFAULT_LOGO && $previous !== '' && $previous !== $path) {
            $this->deleteFile($previous, 'public');
        }

        $this->form->app_logo = null;
    }
}
