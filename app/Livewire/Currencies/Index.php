<?php

namespace App\Livewire\Currencies;

use App\Livewire\Support\MasterIndex;
use App\Models\Currency;
use App\Models\Preference;
use Illuminate\Database\Eloquent\Model;

/**
 * Currencies available to the application.
 *
 * One of them is the display currency chosen in preferences; that one is
 * protected from being deactivated or deleted, because every amount in the
 * application is labelled with it.
 */
class Index extends MasterIndex
{
    protected function model(): string
    {
        return Currency::class;
    }

    protected function permissionPrefix(): string
    {
        return 'currency';
    }

    protected function label(): string
    {
        return 'Currency';
    }

    protected function view(): string
    {
        return 'livewire.currencies.index';
    }

    protected function displayColumn(): string
    {
        return 'code';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['code', 'name', 'symbol'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'name', 'exchange_rate', 'is_active'];
    }

    protected function defaultSortField(): string
    {
        return 'code';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * The code currently selected as the display currency.
     */
    public function activeCode(): string
    {
        return (string) Preference::get('currency', 'PHP');
    }

    public function isInUse(Currency $currency): bool
    {
        return $currency->code === $this->activeCode();
    }

    /**
     * Activate or deactivate, refusing to retire the one in use.
     */
    public function toggleActive(int $id): void
    {
        $this->authorizePermission('edit');

        $currency = $this->findRecord($id);

        if ($currency === null) {
            return;
        }

        if ($currency->is_active && $this->isInUse($currency)) {
            $this->toast('error', "{$currency->code} is the display currency. Choose another one in Preferences first.");

            return;
        }

        $currency->update(['is_active' => ! $currency->is_active]);

        $this->toast('success', sprintf(
            '%s is now %s.',
            $currency->code,
            $currency->is_active ? 'available' : 'unavailable',
        ));
    }

    protected function deleteBlockedReason(Model $record): ?string
    {
        return $this->isInUse($record)
            ? "{$record->code} is the display currency. Choose another one in Preferences first."
            : null;
    }
}
