<?php

namespace App\Livewire\Support;

use App\Livewire\Concerns\WithDataTable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Base list screen for master data.
 *
 * Every configuration list behaves identically - search, sort, paginate, and
 * delete behind a confirmation - so that behaviour lives here once and each
 * module supplies only what differs: its model, its permission prefix and its
 * columns. A concrete index is then a dozen lines, and a fix to the shared
 * behaviour reaches every screen at once.
 *
 * A subclass provides:
 *
 *     protected function model(): string             // Brand::class
 *     protected function permissionPrefix(): string  // 'brand' -> brand-view, brand-delete, …
 *     protected function label(): string             // 'Brand'
 *     protected function view(): string              // 'livewire.brands.index'
 *     protected function searchableColumns(): array
 *     protected function sortableColumns(): array
 *
 * and may override `deleteBlockedReason()` to refuse deletion of a record that
 * is still in use.
 */
#[Layout('components.layouts.app')]
abstract class MasterIndex extends Component
{
    use WithDataTable;

    /** Record queued for deletion, shown in the confirmation modal. */
    public ?int $deletingId = null;

    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * Permission name prefix, e.g. `brand` for brand-view / brand-delete.
     */
    abstract protected function permissionPrefix(): string;

    /**
     * Singular human name used in confirmations and toasts.
     */
    abstract protected function label(): string;

    /**
     * The Blade view rendering the table.
     */
    abstract protected function view(): string;

    /**
     * Column shown when naming the record in a message.
     */
    protected function displayColumn(): string
    {
        return 'name';
    }

    /**
     * Relations to eager load for the table.
     *
     * @return array<int, string>
     */
    protected function withRelations(): array
    {
        return [];
    }

    /**
     * Extra constraints for the listing.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function baseQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Reason this record may not be deleted, or null when deletion is fine.
     * Modules override it to protect records that documents still point at.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        return null;
    }

    /**
     * Name of the confirmation modal, shared by the component and its view.
     */
    public function modalName(): string
    {
        return 'delete-'.$this->permissionPrefix();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizePermission('delete');

        $this->deletingId = $id;

        $this->dispatch('open-modal', name: $this->modalName());
    }

    public function delete(): void
    {
        $this->authorizePermission('delete');

        $record = $this->findRecord($this->deletingId);

        $this->deletingId = null;
        $this->dispatch('close-modal', name: $this->modalName());

        if ($record === null) {
            $this->toast('error', "That {$this->lowerLabel()} no longer exists.");

            return;
        }

        if ($reason = $this->deleteBlockedReason($record)) {
            $this->toast('error', $reason);

            return;
        }

        $name = $record->{$this->displayColumn()};

        $record->delete();

        $this->toast('success', "{$this->label()} {$name} deleted.");
    }

    /**
     * The record named in the confirmation modal.
     */
    public function deletingRecord(): ?Model
    {
        return $this->findRecord($this->deletingId);
    }

    public function render(): View
    {
        return view($this->view(), [
            'records' => $this->rows(),
        ])->title($this->title());
    }

    /**
     * Browser tab title; defaults to the plural label.
     */
    protected function title(): string
    {
        return str($this->label())->plural()->value();
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    protected function rows(): LengthAwarePaginator
    {
        $query = $this->model()::query()->with($this->withRelations());

        return $this->applyDataTable($this->baseQuery($query))->paginate($this->perPage);
    }

    protected function findRecord(?int $id): ?Model
    {
        return $id === null ? null : $this->model()::find($id);
    }

    /**
     * Guard the actions a user can reach without a page load; the route
     * middleware only covers the initial request.
     */
    protected function authorizePermission(string $ability): void
    {
        $permission = "{$this->permissionPrefix()}-{$ability}";

        abort_unless(auth()->user()?->can($permission) ?? false, 403);
    }

    protected function toast(string $type, string $message): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    private function lowerLabel(): string
    {
        return strtolower($this->label());
    }
}
