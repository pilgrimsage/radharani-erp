<?php
namespace App\Livewire\Stock;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Purchase\PurchaseItem;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Models\Stock\QrCode;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

// Inventory list. The add/edit form lives in ItemForm (embedded in this page's view).
class ItemManager extends Component
{
    use WithDataTable;

    // Filters (kept in the URL so a filtered list can be bookmarked / shared)
    #[Url(as: 'category', except: '')]
    public string $categoryFilter = '';
    #[Url(as: 'box', except: '')]
    public string $boxFilter = ''; // '' | box id | 'none' (no packet)
    #[Url(as: 'metal', except: '')]
    public string $metalFilter = '';
    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    public bool $showPendingTags = false;
    public bool $showAssign = false;
    public ?int $assignPacketId = null;

    protected function sortableColumns(): array
    {
        return [
            'code' => 'items.id',
            'category' => 'items.category',
            'metal' => 'items.metal',
            'weight' => 'items.weight',
            'status' => 'items.status',
            'location' => 'packet_code',
            'created' => 'items.created_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['categoryFilter', 'boxFilter', 'metalFilter', 'statusFilter'];
    }

    #[On('item-saved')]
    public function itemSaved(): void
    {
        // Re-render picks up the new/edited piece.
    }

    public function openAssign(): void
    {
        $this->resetValidation();
        $this->assignPacketId = null;
        $this->showAssign = true;
    }

    // One save per piece so every regrouping is written to the history log.
    public function assignSelected(): void
    {
        $this->validate(['assignPacketId' => 'nullable|exists:packets,id'], [], ['assignPacketId' => 'packet']);

        $items = Item::whereIn('id', $this->selected)->get();
        foreach ($items as $item) {
            $item->update(['packet_id' => $this->assignPacketId ?: null]);
        }

        $target = $this->assignPacketId ? 'packet ' . Packet::find($this->assignPacketId)->code : 'no packet';
        $this->showAssign = false;
        $this->selected = [];
        $this->dispatch('toast', message: "{$items->count()} piece(s) moved to {$target}.", type: 'success');
    }

    public function printSelectedQr()
    {
        $ids = Item::whereIn('id', $this->selected)->pluck('id')->map(fn ($id) => QrCode::forTarget('item', $id)->id);

        return $ids->isEmpty() ? null : $this->redirectRoute('stock.qr.print', ['ids' => $ids->implode(',')]);
    }

    public function render()
    {
        $query = Item::query()
            ->select('items.*')
            ->leftJoin('packets', 'packets.id', '=', 'items.packet_id')
            ->leftJoin('boxes', 'boxes.id', '=', 'packets.box_id')
            ->addSelect('packets.code as packet_code', 'boxes.code as box_code', 'packets.box_id as box_id')
            ->when($this->search, fn ($q) => $q->searchAnything($this->search))
            ->when($this->categoryFilter, fn ($q) => $q->where('items.category', $this->categoryFilter))
            ->when($this->metalFilter, fn ($q) => $q->where('items.metal', $this->metalFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('items.status', $this->statusFilter))
            ->when($this->boxFilter === 'none', fn ($q) => $q->whereNull('items.packet_id'))
            ->when(ctype_digit($this->boxFilter), fn ($q) => $q->where('packets.box_id', (int) $this->boxFilter));

        $query = $this->applySorting($query)->orderByDesc('items.id');

        $inStock = Item::where('status', 'in_stock');

        return view('livewire.stock.item-manager', [
            'items' => $query->paginate($this->perPageValue()),
            'categories' => Item::query()->distinct()->orderBy('category')->pluck('category'),
            'boxes' => Box::orderBy('code')->get(['id', 'code', 'label']),
            'packetsByBox' => $this->showAssign
                ? Packet::with('box:id,code')->orderBy('code')->get(['id', 'code', 'label', 'box_id'])->groupBy(fn ($p) => $p->box?->code ?? 'Not in a box')
                : collect(),
            'pendingTags' => PurchaseItem::where('tag_pending', true)->whereNull('item_id')
                ->with('purchase.vendor')->orderByDesc('id')->get(),
            'stats' => [
                'inStock' => (clone $inStock)->count(),
                'inStockWeight' => (float) (clone $inStock)->sum('weight'),
                'dispatched' => Item::where('status', 'dispatched')->count(),
                'pending' => Item::where('status', 'pending_review')->count(),
                'reserved' => Item::where('status', 'reserved')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Inventory · Radharani Jewellery ERP']);
    }
}
