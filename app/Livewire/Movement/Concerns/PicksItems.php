<?php
namespace App\Livewire\Movement\Concerns;

use App\Models\Stock\Item;
use App\Support\StockLookup;
use Illuminate\Support\Collection;

/**
 * A scan-or-search "basket" of pieces for the dispatch screens (Karigar,
 * Hallmarking, Photo / Custom). Pair with <x-movement.item-picker>.
 * The host component says which statuses may be picked via pickableStatuses().
 */
trait PicksItems
{
    /** @var array<int, int> */
    public array $basket = [];

    public string $pickSearch = '';

    abstract protected function pickableStatuses(): array;

    // Enter in the picker: an exact code / QR scan wins, otherwise a single search hit.
    public function addByCode(string $raw): void
    {
        $this->resetErrorBag('pickSearch');
        $item = StockLookup::item($raw);

        // A whole box or packet (8 Oct change list, 8.1): every piece in it that can go is added.
        if (! $item && ($found = StockLookup::container($raw))) {
            $this->addContainer($found['type'], $found['model']);

            return;
        }

        if (! $item) {
            $hits = $this->pickResults();
            if ($hits->count() !== 1) {
                $this->addError('pickSearch', $hits->isEmpty()
                    ? 'No piece found for "' . StockLookup::normalize($raw) . '".'
                    : 'Several pieces match. Pick one from the list.');
                return;
            }
            $item = $hits->first();
        }

        $this->addToBasket($item->id);
    }

    protected function addContainer(string $type, $model): void
    {
        $items = $type === 'packet'
            ? Item::where('packet_id', $model->id)
            : Item::whereIn('packet_id', $model->packets()->select('id'));
        $items = $items->whereIn('status', $this->pickableStatuses())->pluck('id')->all();

        if (! $items) {
            $this->addError('pickSearch', "{$model->code} has no pieces that can go right now.");

            return;
        }

        $before = count($this->basket);
        $this->basket = array_values(array_unique(array_merge($this->basket, $items)));
        $this->pickSearch = '';
        $this->dispatch('toast', message: (count($this->basket) - $before) . " pieces from {$model->code} added.", type: 'info');
        $this->dispatch('picker-ready');
    }

    public function addToBasket(int $id): void
    {
        $this->resetErrorBag(['pickSearch', 'basket']);
        $item = Item::find($id);

        if (! $item) {
            return;
        }
        if (in_array($item->id, $this->basket, true)) {
            $this->pickSearch = '';
            $this->dispatch('picker-ready');
            return;
        }
        if (! in_array($item->status, $this->pickableStatuses(), true)) {
            $this->addError('pickSearch', "{$item->label} can't go: it is " . str_replace('_', ' ', $item->status) . ' right now.');
            return;
        }

        $this->basket[] = $item->id;
        $this->pickSearch = '';
        $this->dispatch('picker-ready');
    }

    public function removeFromBasket(int $id): void
    {
        $this->basket = array_values(array_filter($this->basket, fn ($b) => $b !== $id));
    }

    public function clearBasket(): void
    {
        $this->basket = [];
    }

    protected function basketItems(): Collection
    {
        if (! $this->basket) {
            return collect();
        }
        $order = array_flip($this->basket);

        return Item::with('packet:id,code')->whereIn('id', $this->basket)->get()
            ->sortBy(fn ($i) => $order[$i->id] ?? 0)->values();
    }

    protected function pickResults(): Collection
    {
        $term = trim($this->pickSearch);
        if ($term === '') {
            return collect();
        }

        return Item::with('packet:id,code')
            ->whereIn('status', $this->pickableStatuses())
            ->whereNotIn('id', $this->basket)
            ->where(fn ($q) => $q->where('huid_code', 'like', "%{$term}%")
                ->orWhere('internal_code', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"))
            ->orderBy('category')->limit(8)->get();
    }
}
