<?php

namespace App\Livewire\Users;

use App\Livewire\Forms\UserForm;
use App\Livewire\Support\MasterForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Form as LivewireForm;
use Spatie\Permission\Models\Role;

/**
 * Create and edit screen for a user account.
 *
 * Editing the seeded administrator, or your own account, is refused here: the
 * first would let someone lock everyone out, the second belongs in profile
 * settings where the session is handled properly.
 */
class Form extends MasterForm
{
    /** The seeded administrator account. */
    private const PROTECTED_ID = 1;

    public UserForm $form;

    public ?User $user = null;

    public function mount(?User $user = null): void
    {
        if (! $user?->exists) {
            return;
        }

        if ($user->id === self::PROTECTED_ID) {
            abort(403, 'The system administrator account cannot be edited here.');
        }

        // Changing your own account belongs in profile settings, where the
        // session and the password confirmation are handled properly.
        if ($user->id === auth()->id()) {
            session()->flash('error', 'Use your own profile settings to change your account.');

            $this->redirectRoute('users.index', navigate: true);

            return;
        }

        $this->user = $user;
        $this->form->setUser($user);
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->user;
    }

    protected function indexRoute(): string
    {
        return 'users.index';
    }

    protected function label(): string
    {
        return 'User';
    }

    protected function view(): string
    {
        return 'livewire.users.form';
    }

    protected function title(): string
    {
        return $this->isEditing() ? "Edit {$this->user->name}" : 'Create User';
    }

    protected function recordName(?Model $record): string
    {
        return (string) ($record?->name ?? '');
    }

    /**
     * The roles that can be assigned, with the permission count each carries so
     * the choice is not made blind.
     *
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::query()->withCount('permissions')->orderBy('name')->get();
    }

    /**
     * Password rules, shown next to the field rather than only on failure.
     *
     * @return array<int, string>
     */
    public function passwordRules(): array
    {
        return [
            'At least 8 characters',
            'Upper and lower case letters',
            'At least one number',
            'At least one symbol',
            'Not found in known breach lists',
        ];
    }
}
