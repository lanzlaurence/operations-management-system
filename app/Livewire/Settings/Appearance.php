<?php

namespace App\Livewire\Settings;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Light, dark or system.
 *
 * The choice lives in the `appearance` cookie and is applied by Alpine before
 * the first paint, so there is nothing to save server-side - this screen only
 * exists so the setting has a home next to the other personal settings.
 */
#[Layout('components.layouts.app')]
#[Title('Appearance settings')]
class Appearance extends Component
{
    public function render(): View
    {
        return view('livewire.settings.appearance');
    }
}
