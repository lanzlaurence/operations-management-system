<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Form;

/**
 * The user form, shared by the create and edit screens.
 *
 * Two things are worth knowing:
 *
 *  - the password is assigned in plain text and hashed by the model's `hashed`
 *    cast, which skips values that are already hashed;
 *  - clearing the lock also clears the failed login counter, otherwise the
 *    next wrong password locks the account again immediately.
 */
class UserForm extends Form
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $email_verified = true;

    public bool $force_password_change = true;

    public bool $is_active = true;

    public bool $is_locked = false;

    /**
     * Role names assigned to the user.
     *
     * @var array<int, string>
     */
    public array $roles = [];

    public function setUser(User $user): void
    {
        $this->user = $user;

        $this->name = $user->name;
        $this->email = $user->email;
        $this->email_verified = $user->email_verified_at !== null;
        $this->force_password_change = (bool) $user->force_password_change;
        $this->is_active = (bool) $user->is_active;
        $this->is_locked = (bool) $user->is_locked;
        $this->roles = $user->roles->pluck('name')->all();

        // Editing never carries the existing password forward.
        $this->password = '';
        $this->password_confirmation = '';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user?->id)->whereNull('deleted_at'),
            ],
            // Required when creating, optional when editing.
            'password' => [
                $this->user === null ? 'required' : 'nullable',
                'confirmed',
                Password::defaults(),
            ],
            'email_verified' => ['boolean'],
            'force_password_change' => ['boolean'],
            'is_active' => ['boolean'],
            'is_locked' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the person\'s name.',
            'email.required' => 'Enter an email address; it is what they sign in with.',
            'email.unique' => 'That email address is already registered.',
            'password.required' => 'Set an initial password.',
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'email_verified' => 'email verified',
            'force_password_change' => 'force password change',
            'is_active' => 'active',
            'is_locked' => 'locked',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['name'] = trim((string) ($attributes['name'] ?? ''));
        $attributes['email'] = strtolower(trim((string) ($attributes['email'] ?? '')));

        return $attributes;
    }

    /**
     * Create or update the account and sync its roles.
     */
    public function save(): User
    {
        $data = $this->validate();

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'force_password_change' => (bool) $data['force_password_change'],
            'is_active' => (bool) $data['is_active'],
            'is_locked' => (bool) $data['is_locked'],
        ];

        if ($this->user === null) {
            $user = User::create([
                ...$attributes,
                'email_verified_at' => $data['email_verified'] ? now() : null,
                'password' => $data['password'],
                'password_changed_at' => now(),
            ]);

            $user->syncRoles($data['roles'] ?? []);

            return $user;
        }

        // Keep the original verification timestamp when it is still ticked.
        $attributes['email_verified_at'] = $data['email_verified']
            ? ($this->user->email_verified_at ?? now())
            : null;

        // Unlocking has to clear the counter that caused the lock.
        if (! $attributes['is_locked']) {
            $attributes['login_attempts'] = 0;
        }

        if (($data['password'] ?? '') !== '') {
            $attributes['password'] = $data['password'];
            $attributes['password_changed_at'] = now();
        }

        $this->user->update($attributes);
        $this->user->syncRoles($data['roles'] ?? []);

        $this->password = '';
        $this->password_confirmation = '';

        return $this->user;
    }
}
