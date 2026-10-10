<?php
namespace App\Livewire\Stock;

use App\Models\Movement\Movement;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Models\Stock\QrCode;
use App\Services\PricingService;
use App\Services\StockHistoryService;
use Livewire\Attributes\On;
use Livewire\Component;

class ItemDetail extends Component
{
    public Item $item;

    public bool $showMove = false;
    public ?int $moveToPacketId = null;

    public function mount(Item $item): void
    {
        $this->item = $item;
    }

    #[On('item-saved')]
    public function refreshItem(): void
    {
        $this->item->refresh();
    }

    public function openMove(): void
    {
        $this->resetValidation();
        $this->moveToPacketId = $this->item->packet_id;
        $this->showMove = true;
    }

    public function move(): void
    {
        $this->validate(['moveToPacketId' => 'nullable|exists:packets,id'], [], ['moveToPacketId' => 'packet']);

        $this->item->update(['packet_id' => $this->moveToPacketId ?: null]); // logged in the item's history
        $this->showMove = false;

        $where = $this->moveToPacketId ? 'packet ' . Packet::find($this->moveToPacketId)->code : 'no packet';
        $this->dispatch('toast', message: "{$this->item->label} moved to {$where}.", type: 'success');
    }

    // Soft delete: hidden everywhere, kept in the audit trail.
    public function deleteItem()
    {
        if ($reason = $this->item->deletionBlocker()) {
            $this->dispatch('toast', message: $reason, type: 'error');

            return;
        }

        $label = $this->item->label;
        $this->item->delete();
        activity('stock')->performedOn($this->item)->causedBy(auth()->user())->event('deleted')->log("Piece {$label} deleted");

        session()->flash('toast', "{$label} deleted. It stays in the audit trail.");

        return $this->redirectRoute('stock.items');
    }

    public function issueQr(): void
    {
        QrCode::forTarget('item', $this->item->id);
        $this->dispatch('toast', message: 'QR label issued. Print it from the panel.', type: 'success');
    }

    public function render(StockHistoryService $history, PricingService $pricing)
    {
        $this->item->load('packet.box', 'sourcePurchaseItem.purchase.vendor', 'sourceKarigarBatch');

        $sale = $this->item->sales()->with('customer')->latest('sales.id')->first();
        $lastOut = in_array($this->item->status, ['dispatched', 'pending_review'], true)
            ? Movement::where('trackable_type', 'item')->where('trackable_id', $this->item->id)->latest('id')->first()
            : null;

        return view('livewire.stock.item-detail', [
            'events' => $history->forItem($this->item),
            'pair' => $this->item->pair_group_id ? $this->item->pairedWith()->first() : null,
            'price' => $this->item->status === 'sold' ? null : $pricing->breakdown($this->item),
            'sale' => $sale,
            'lastMovement' => $lastOut,
            'qr' => $this->item->qrCodes()->latest('id')->first(),
            'packetsByBox' => $this->showMove
                ? Packet::with('box:id,code')->orderBy('code')->get(['id', 'code', 'label', 'box_id'])->groupBy(fn ($p) => $p->box?->code ?? 'Not in a box')
                : collect(),
        ])->layout('components.layouts.app', ['title' => "Item {$this->item->label} · Radharani Jewellery"]);
    }
}
