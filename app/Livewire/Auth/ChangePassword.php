<?php

namespace App\Livewire\Auth;

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * The forced password change.
 *
 * Reached through the `password.changed` middleware, which holds an account
 * here until it sets its own password - the state a new account and an
 * administrator-set password both start in. Clearing the flag is what releases
 * the rest of the application.
 */
#[Layout('components.layouts.auth')]
#[Title('Change password')]
class ChangePassword extends Component
{
    use PasswordValidationRules;

    #[Validate]
    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = Auth::user();

        $user->update([
            'password' => $this->password,
            'password_changed_at' => now(),
            'force_password_change' => false,
        ]);

        $this->reset('password', 'password_confirmation');

        session()->flash('success', 'Password changed. Welcome in.');

        $this->redirectRoute('dashboard', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.change-password');
    }
}
