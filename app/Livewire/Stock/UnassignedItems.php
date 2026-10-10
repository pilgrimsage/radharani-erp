<?php
namespace App\Livewire\Stock;

use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pieces that are in no packet yet (8 Oct change list, 4.2), grouped into batches by the
 * time they were entered. Any piece can be edited at any time; every change is logged
 * through the item's own history.
 */
class UnassignedItems extends Component
{
    public string $search = '';

    /** @var array<int, string> */
    public array $selected = [];

    public bool $showMove = false;
    public ?int $moveToPacketId = null;

    #[On('item-saved')]
    public function refreshList(): void {}

    public function openMove(): void
    {
        $this->resetValidation();
        $this->moveToPacketId = null;
        $this->showMove = true;
    }

    // One by one, not a mass update, so each move lands in the piece's history.
    public function moveSelected(): void
    {
        $this->validate(['moveToPacketId' => 'required|exists:packets,id,deleted_at,NULL'], ['moveToPacketId.required' => 'Choose a packet.'], ['moveToPacketId' => 'packet']);

        $items = Item::whereNull('packet_id')->whereIn('id', $this->selected)->get();
        foreach ($items as $item) {
            $item->update(['packet_id' => $this->moveToPacketId]);
        }

        $code = Packet::find($this->moveToPacketId)->code;
        $this->showMove = false;
        $this->selected = [];
        $this->dispatch('toast', message: "{$items->count()} piece(s) moved to {$code}.", type: 'success');
    }

    public function toggleBatch(string $key, bool $on): void
    {
        $ids = $this->query()->get(['id', 'entry_batch_id', 'created_at'])
            ->filter(fn ($i) => $this->batchKey($i) === $key)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selected = $on
            ? array_values(array_unique(array_merge($this->selected, $ids)))
            : array_values(array_diff($this->selected, $ids));
    }

    private function query()
    {
        return Item::query()->whereNull('packet_id')->where('status', '!=', 'sold')
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('huid_code', 'like', "%{$this->search}%")
                ->orWhere('internal_code', 'like', "%{$this->search}%")
                ->orWhere('category', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")))
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    // Pieces without a batch (added one by one) group by the minute they were entered.
    private function batchKey(Item $item): string
    {
        return $item->entry_batch_id ? 'b' . $item->entry_batch_id : 'm' . $item->created_at->format('YmdHi');
    }

    public function render()
    {
        $items = $this->query()->limit(600)->get();

        $batches = $items->groupBy(fn ($i) => $this->batchKey($i))->map(fn ($rows, $key) => [
            'key' => $key,
            'label' => $rows->first()->created_at->format('j M Y, g:i a'),
            'kind' => str_starts_with($key, 'b') ? 'Import' : 'Entered one by one',
            'rows' => $rows,
            'weight' => $rows->sum('weight'),
            'ids' => $rows->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ])->values();

        return view('livewire.stock.unassigned-items', [
            'batches' => $batches,
            'total' => $items->count(),
            'packets' => $this->showMove ? Packet::with('box:id,code')->orderBy('code')->get(['id', 'code', 'label', 'box_id'])->groupBy(fn ($p) => $p->box?->code ?? 'Not in a box') : collect(),
        ])->layout('components.layouts.app', ['title' => 'Unassigned items · Radharani Jewellery ERP']);
    }
}
