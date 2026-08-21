<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Your own profile: name, email address, and deleting your account.
 *
 * Changing the email clears its verification, because the new address has not
 * been proven yet - the same rule the previous controller applied.
 */
#[Layout('components.layouts.app')]
#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    /** Password typed into the delete-account confirmation. */
    public string $deletePassword = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->profileRules(Auth::id());
    }

    public function save(): void
    {
        $data = $this->validate();

        $user = Auth::user();

        $user->fill($data);

        // A changed address has to be verified again.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('toast', type: 'success', message: 'Profile updated.');
    }

    /**
     * Send a fresh verification link to the current address.
     */
    public function resendVerification(): void
    {
        Auth::user()->sendEmailVerificationNotification();

        $this->dispatch('toast', type: 'success', message: 'Verification link sent.');
    }

    /**
     * Delete your own account, confirmed with your password.
     */
    public function deleteAccount(): void
    {
        $this->validate(
            ['deletePassword' => ['required', 'string', 'current_password']],
            ['deletePassword.current_password' => 'That password is incorrect.'],
            ['deletePassword' => 'password'],
        );

        $user = Auth::user();

        Auth::logout();

        $user->delete();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: false);
    }

    public function mustVerifyEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail;
    }

    public function isVerified(): bool
    {
        return Auth::user()->email_verified_at !== null;
    }

    public function render(): View
    {
        return view('livewire.settings.profile');
    }
}
