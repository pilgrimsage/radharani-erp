<?php
namespace App\Livewire\Movement;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Movement\Movement;
use App\Models\Purchase\PurchaseItem;
use App\Models\Stock\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin-only list of everything unfinished (#9):
 *  - returns: pieces back from a karigar or hallmarking, and new pieces made
 *    from raw material, all sitting in pending_review
 *  - tags:    raw-material purchase lines not yet tagged into pieces
 * Confirming a piece is what moves it into normal, sellable stock. The only
 * change to the movement row is approved_by, set once (rule 1's exception).
 */
class PendingReviewQueue extends Component
{
    use WithDataTable;

    public const RETURN_TYPES = ['karigar_in', 'hallmark_in'];

    #[Url(except: 'returns')]
    public string $tab = 'returns'; // returns | tags

    #[Url(as: 'from', except: '')]
    public string $source = ''; // '' | karigar | hallmark | new

    protected function sortableColumns(): array
    {
        return [
            'code' => 'items.id',
            'category' => 'items.category',
            'weight' => 'items.weight',
            'since' => 'returned_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['since', 'asc']; // oldest first: work through the queue in order
    }

    protected function filterProperties(): array
    {
        return ['source'];
    }

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('movement.approve'), 403);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['returns', 'tags'], true)) {
            $this->tab = $tab;
            $this->selected = [];
        }
    }

    public function showSource(string $source): void
    {
        $this->tab = 'returns';
        $this->source = $source;
        $this->selected = [];
        $this->resetPage();
    }

    #[On('item-saved')]
    public function itemSaved(): void
    {
        // Re-render: a reviewed piece or a newly tagged purchase line.
    }

    public function confirmClose(int $itemId): void
    {
        abort_unless(Auth::user()?->can('movement.approve'), 403);

        $item = Item::find($itemId);
        if (! $item || $item->status !== 'pending_review') {
            $this->dispatch('toast', message: 'That piece is no longer waiting for review.', type: 'info');
            return;
        }

        $this->approve($item);
        $this->selected = array_values(array_diff($this->selected, [(string) $itemId]));
        $this->dispatch('toast', message: "{$item->label} confirmed into stock.", type: 'success');
    }

    public function confirmSelected(): void
    {
        abort_unless(Auth::user()?->can('movement.approve'), 403);

        $items = Item::whereIn('id', $this->selected)->where('status', 'pending_review')->get();
        DB::transaction(fn () => $items->each(fn ($item) => $this->approve($item)));

        $this->selected = [];
        $this->dispatch('toast', message: "{$items->count()} " . \Illuminate\Support\Str::plural('piece', $items->count()) . ' confirmed into stock.', type: 'success');
    }

    private function approve(Item $item): void
    {
        // Record the review on the return movement itself, set once and never overwritten.
        $return = Movement::where('trackable_type', 'item')->where('trackable_id', $item->id)
            ->whereIn('movement_type', self::RETURN_TYPES)
            ->latest('id')->first();

        if ($return && ! $return->approved_by) {
            $return->update(['approved_by' => Auth::id()]);
        }

        $item->update(['status' => 'in_stock']);
    }

    private function lastReturnSub(string $column)
    {
        return Movement::select($column)
            ->where('trackable_type', 'item')
            ->whereColumn('trackable_id', 'items.id')
            ->whereIn('movement_type', self::RETURN_TYPES)
            ->orderByDesc('id')->limit(1);
    }

    public function render()
    {
        $query = Item::query()->where('status', 'pending_review')
            ->select('items.*')
            ->addSelect(['last_return_id' => $this->lastReturnSub('id')])
            ->addSelect(['returned_at' => $this->lastReturnSub('created_at')])
            ->when($this->search !== '', fn ($q) => $q->searchAnything($this->search))
            ->when($this->source === 'new', fn ($q) => $q->whereNotNull('source_karigar_batch_id'))
            ->when(in_array($this->source, ['karigar', 'hallmark'], true), fn ($q) => $q
                ->whereNull('source_karigar_batch_id')
                ->where(fn ($s) => $s->select('movement_type')->from('movements')
                    ->where('trackable_type', 'item')->whereColumn('trackable_id', 'items.id')
                    ->whereIn('movement_type', self::RETURN_TYPES)->orderByDesc('id')->limit(1), $this->source . '_in'));

        $items = $this->applySorting($query)->paginate($this->perPageValue());

        // Batch-load each row's return movement and the dispatch before it (for "sent at" weight).
        $ids = $items->pluck('id');
        $returns = Movement::whereIn('id', $items->pluck('last_return_id')->filter())->with('user:id,name')->get()->keyBy('id');
        $dispatches = Movement::where('trackable_type', 'item')->whereIn('trackable_id', $ids)
            ->whereIn('movement_type', ['karigar_out', 'hallmark_out'])
            ->orderByDesc('id')->get()->groupBy('trackable_id');

        $rows = $items->getCollection()->map(function ($item) use ($returns, $dispatches) {
            $ret = $returns[$item->last_return_id] ?? null;
            $out = $ret ? ($dispatches[$item->id] ?? collect())->first(fn ($m) => $m->id < $ret->id) : null;

            return [
                'item' => $item,
                'return' => $ret,
                'kind' => $item->source_karigar_batch_id ? 'new' : ($ret ? str_replace('_in', '', $ret->movement_type) : null),
                'sent' => $item->source_karigar_batch_id ? $ret?->weight_at_dispatch : $out?->weight_at_dispatch,
            ];
        });

        $pendingBase = Item::where('status', 'pending_review');
        $lastType = fn ($type) => (clone $pendingBase)->whereNull('source_karigar_batch_id')
            ->where(fn ($s) => $s->select('movement_type')->from('movements')
                ->where('trackable_type', 'item')->whereColumn('trackable_id', 'items.id')
                ->whereIn('movement_type', self::RETURN_TYPES)->orderByDesc('id')->limit(1), $type)->count();

        return view('livewire.movement.pending-review-queue', [
            'items' => $items,
            'rows' => $rows,
            'tags' => $this->tab === 'tags'
                ? PurchaseItem::with('purchase.vendor')->where('tag_pending', true)->whereNull('item_id')->orderBy('id')->get()
                : collect(),
            'counts' => [
                'returns' => (clone $pendingBase)->count(),
                'karigar' => $lastType('karigar_in'),
                'hallmark' => $lastType('hallmark_in'),
                'new' => (clone $pendingBase)->whereNotNull('source_karigar_batch_id')->count(),
                'tags' => PurchaseItem::where('tag_pending', true)->whereNull('item_id')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Pending Review · Radharani Jewellery']);
    }
}
