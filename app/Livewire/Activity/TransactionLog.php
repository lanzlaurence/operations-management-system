<?php

namespace App\Livewire\Activity;

use App\Enums\DocumentAction;
use App\Livewire\Concerns\WithDataTable;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\TransactionLog as TransactionLogModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Audit trail for the transactional documents.
 *
 * Append-only, so this screen only ever reads. Filtering happens in SQL against
 * the indexes added for it, and the whole trail paginates rather than capping
 * itself at a page of recent rows, so older history stays reachable.
 */
#[Layout('components.layouts.app')]
#[Title('Transaction Log')]
class TransactionLog extends Component
{
    use WithDataTable;

    #[Url(as: 'document', except: '', history: true)]
    public string $documentFilter = '';

    #[Url(as: 'action', except: '', history: true)]
    public string $actionFilter = '';

    #[Url(as: 'from', except: '', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', except: '', history: true)]
    public string $toDate = '';

    #[Url(as: 'quiet', except: false, history: true)]
    public bool $hideSystem = false;

    /**
     * The document types this trail covers, keyed by the short name used in the
     * query string.
     *
     * @var array<string, class-string<Model>>
     */
    private const DOCUMENT_TYPES = [
        'PurchaseOrder' => PurchaseOrder::class,
        'GoodsReceipt' => GoodsReceipt::class,
        'SalesOrder' => SalesOrder::class,
        'GoodsIssue' => GoodsIssue::class,
    ];

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['remarks'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['created_at', 'action'];
    }

    protected function defaultSortField(): string
    {
        return 'created_at';
    }

    protected function defaultSortDirection(): string
    {
        return 'desc';
    }

    /**
     * Searching has to reach across the morph relation to the document code and
     * out to the acting user, which `whereHas` cannot do for a `morphTo`.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query
                ->where('remarks', 'like', "%{$term}%")
                ->orWhere('from_status', 'like', "%{$term}%")
                ->orWhere('to_status', 'like', "%{$term}%")
                ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$term}%"))
                ->orWhereHasMorph(
                    'loggable',
                    array_values(self::DOCUMENT_TYPES),
                    function (Builder $document, string $type) use ($term): void {
                        $document->where('code', 'like', "%{$term}%");

                        // Only the orders carry a counterparty reference; the
                        // receipts and issues have no such column.
                        if (in_array($type, [PurchaseOrder::class, SalesOrder::class], true)) {
                            $document->orWhere('reference_no', 'like', "%{$term}%");
                        }
                    },
                );
        });
    }

    public function updatedDocumentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
    }

    public function updatedHideSystem(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->documentFilter !== ''
            || $this->actionFilter !== ''
            || $this->fromDate !== ''
            || $this->toDate !== ''
            || $this->hideSystem;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->documentFilter = '';
        $this->actionFilter = '';
        $this->fromDate = '';
        $this->toDate = '';
        $this->hideSystem = false;
        $this->resetPage();
    }

    /**
     * Where a log entry's document lives, or null when the type is unknown.
     */
    public function documentUrl(TransactionLogModel $log): ?string
    {
        return match ($log->documentType()) {
            'PurchaseOrder' => route('purchase-orders.show', $log->loggable_id),
            'GoodsReceipt' => route('goods-receipts.show', $log->loggable_id),
            'SalesOrder' => route('sales-orders.show', $log->loggable_id),
            'GoodsIssue' => route('goods-issues.show', $log->loggable_id),
            default => null,
        };
    }

    /**
     * How many entries each action accounts for under the current filters,
     * which is the quickest read on what has been happening.
     *
     * @return array<string, int>
     */
    public function actionBreakdown(): array
    {
        return $this->filteredQuery()
            ->reorder()
            ->select([])
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->orderByDesc('total')
            ->pluck('total', 'action')
            ->all();
    }

    public function render(): View
    {
        return view('livewire.activity.transaction-log', [
            'records' => $this->rows(),
            'actionOptions' => DocumentAction::options(),
            'documentOptions' => array_combine(
                array_keys(self::DOCUMENT_TYPES),
                array_map(
                    fn (string $type): string => str(class_basename($type))->headline()->value(),
                    self::DOCUMENT_TYPES,
                ),
            ),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, TransactionLogModel>
     */
    private function rows(): LengthAwarePaginator
    {
        return $this->applyDataTable($this->filteredQuery())
            ->with(['user:id,name', 'loggable'])
            ->paginate($this->perPage);
    }

    /**
     * The trail with the filters applied but without paging or ordering, so it
     * can serve both the table and the breakdown.
     *
     * @return Builder<TransactionLogModel>
     */
    private function filteredQuery(): Builder
    {
        return TransactionLogModel::query()
            ->when(
                $this->documentFilter !== '' && isset(self::DOCUMENT_TYPES[$this->documentFilter]),
                fn (Builder $query) => $query->where('loggable_type', self::DOCUMENT_TYPES[$this->documentFilter]),
            )
            ->when($this->actionFilter !== '', fn (Builder $query) => $query->where('action', $this->actionFilter))
            ->when($this->hideSystem, fn (Builder $query) => $query->userActions())
            ->betweenDates($this->fromDate ?: null, $this->toDate ?: null);
    }
}
