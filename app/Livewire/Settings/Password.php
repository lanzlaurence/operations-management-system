<?php

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Change your own password.
 *
 * Requires the current password, which is what separates this from an
 * administrator setting someone else's password on the users screen.
 */
#[Layout('components.layouts.app')]
#[Title('Password settings')]
class Password extends Component
{
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'That is not your current password.',
            'password.confirmed' => 'The two new passwords do not match.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'current_password' => 'current password',
            'password' => 'new password',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Auth::user()->update([
            'password' => $this->password,
            'password_changed_at' => now(),
            'force_password_change' => false,
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('toast', type: 'success', message: 'Password changed.');
    }

    public function lastChangedAt(): ?string
    {
        return Auth::user()->password_changed_at?->format('M d, Y g:i A');
    }

    public function render(): View
    {
        return view('livewire.settings.password');
    }
}
