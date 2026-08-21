<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Search, sort and pagination for every index screen.
 *
 * The work happens in SQL, so a 50 000 row table costs the same as a 50 row
 * one and the browser only ever receives one page.
 *
 * A component using this trait declares which columns may be searched and
 * sorted, then pipes its query through `applyDataTable()`:
 *
 *     protected function searchableColumns(): array { return ['acronym', 'description']; }
 *     protected function sortableColumns(): array   { return ['acronym', 'created_at']; }
 *
 *     $this->applyDataTable(Uom::query())->paginate($this->perPage);
 *
 * The state lives in the query string, so a filtered list can be bookmarked,
 * shared and survives a browser refresh.
 */
trait WithDataTable
{
    use WithPagination;

    #[Url(as: 'q', except: '', history: true, keep: false)]
    public string $search = '';

    #[Url(as: 'sort', except: '', history: true)]
    public string $sortField = '';

    #[Url(as: 'dir', except: 'asc', history: true)]
    public string $sortDirection = 'asc';

    #[Url(as: 'per', except: 25, history: true)]
    public int $perPage = 25;

    /**
     * Page sizes offered in the table footer.
     *
     * @var array<int, int>
     */
    public array $perPageOptions = [10, 25, 50, 100];

    /**
     * Columns matched by the search box.
     *
     * Dotted names search a relation, e.g. `material.name`.
     *
     * @return array<int, string>
     */
    abstract protected function searchableColumns(): array;

    /**
     * Columns the user may sort by. Anything outside this list is ignored,
     * which is what keeps the query-string sort safe to interpolate.
     *
     * @return array<int, string>
     */
    abstract protected function sortableColumns(): array;

    protected function defaultSortField(): string
    {
        return 'created_at';
    }

    protected function defaultSortDirection(): string
    {
        return 'desc';
    }

    /**
     * Toggle direction when the same column is clicked again, otherwise sort
     * the new column ascending.
     */
    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '';
    }

    /**
     * Apply the search term and the ordering to a query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyDataTable(Builder $query): Builder
    {
        return $this->applySort($this->applySearch($query));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);

        if ($term === '' || $this->searchableColumns() === []) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            foreach ($this->searchableColumns() as $column) {
                if (! str_contains($column, '.')) {
                    $query->orWhere($column, 'like', "%{$term}%");

                    continue;
                }

                // `relation.column` (or `a.b.column`) searches through the relation.
                $segments = explode('.', $column);
                $field = array_pop($segments);

                $query->orWhereHas(
                    implode('.', $segments),
                    fn (Builder $relation) => $relation->where($field, 'like', "%{$term}%"),
                );
            }
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySort(Builder $query): Builder
    {
        $field = in_array($this->sortField, $this->sortableColumns(), true)
            ? $this->sortField
            : $this->defaultSortField();

        $direction = $this->sortField === '' ? $this->defaultSortDirection() : $this->sortDirection;
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if (str_contains($field, '.')) {
            // Sorting by a relation column is opted into per component; until a
            // screen needs it, fall back to the local default column.
            $field = $this->defaultSortField();
        }

        return $query->orderBy($field, $direction);
    }

    /**
     * Livewire renders pagination with this view (DaisyUI join buttons).
     */
    public function paginationView(): string
    {
        return 'components.pagination';
    }

    public function paginationSimpleView(): string
    {
        return 'components.pagination';
    }
}
