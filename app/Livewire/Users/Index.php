<?php

namespace App\Livewire\Users;

use App\Livewire\Support\MasterIndex;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Role;

/**
 * User accounts.
 *
 * Two accounts can never be touched from here: the first user (the
 * administrator the system was seeded with) and whoever is currently signed
 * in - locking yourself out of your own session is the one mistake this screen
 * must not allow.
 */
class Index extends MasterIndex
{
    /** The seeded administrator account. */
    private const PROTECTED_ID = 1;

    #[Url(as: 'role', except: '', history: true)]
    public string $roleFilter = '';

    #[Url(as: 'state', except: '', history: true)]
    public string $stateFilter = '';

    protected function model(): string
    {
        return User::class;
    }

    protected function permissionPrefix(): string
    {
        return 'user';
    }

    protected function label(): string
    {
        return 'User';
    }

    protected function view(): string
    {
        return 'livewire.users.index';
    }

    /**
     * @return array<int, string>
     */
    protected function withRelations(): array
    {
        return ['roles:id,name'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'email'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'email', 'created_at', 'password_changed_at'];
    }

    protected function defaultSortField(): string
    {
        return 'name';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function baseQuery(Builder $query): Builder
    {
        return $query
            ->when($this->roleFilter !== '', fn (Builder $q) => $q->whereHas(
                'roles',
                fn (Builder $role) => $role->where('name', $this->roleFilter),
            ))
            ->when($this->stateFilter === 'active', fn (Builder $q) => $q->where('is_active', true)->where('is_locked', false))
            ->when($this->stateFilter === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when($this->stateFilter === 'locked', fn (Builder $q) => $q->where('is_locked', true))
            ->when($this->stateFilter === 'unverified', fn (Builder $q) => $q->whereNull('email_verified_at'))
            ->when($this->stateFilter === 'must_change', fn (Builder $q) => $q->where('force_password_change', true));
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStateFilter(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->roleFilter !== '' || $this->stateFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->stateFilter = '';
        $this->resetPage();
    }

    /**
     * Whether this account may be changed at all.
     */
    public function isProtected(User $user): bool
    {
        return $user->id === self::PROTECTED_ID || $user->id === auth()->id();
    }

    /**
     * Why this account is protected, for the tooltip on the disabled buttons.
     */
    public function protectedReason(User $user): ?string
    {
        return match (true) {
            $user->id === self::PROTECTED_ID => 'The system administrator account cannot be changed here.',
            $user->id === auth()->id() => 'Use your own profile settings to change your account.',
            default => null,
        };
    }

    /**
     * Release a locked account and reset its failed login counter.
     */
    public function unlock(int $id): void
    {
        $this->authorizePermission('edit');

        $user = $this->findRecord($id);

        if ($user === null || ! $user->is_locked) {
            return;
        }

        $user->update(['is_locked' => false, 'login_attempts' => 0]);

        $this->toast('success', "{$user->name} can sign in again.");
    }

    /**
     * Activate or deactivate an account, which is how access is revoked
     * without deleting the person's history.
     */
    public function toggleActive(int $id): void
    {
        $this->authorizePermission('edit');

        $user = $this->findRecord($id);

        if ($user === null) {
            return;
        }

        if ($this->isProtected($user)) {
            $this->toast('error', $this->protectedReason($user) ?? 'This account cannot be changed.');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->toast('success', sprintf(
            '%s is now %s.',
            $user->name,
            $user->is_active ? 'active' : 'deactivated',
        ));
    }

    protected function deleteBlockedReason(Model $record): ?string
    {
        return $this->protectedReason($record);
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->pluck('name', 'name'),
            'states' => [
                'active' => 'Active',
                'inactive' => 'Deactivated',
                'locked' => 'Locked out',
                'unverified' => 'Email unverified',
                'must_change' => 'Must change password',
            ],
        ];
    }
}
