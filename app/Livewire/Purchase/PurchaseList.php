<?php
namespace App\Livewire\Purchase;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Purchase\Purchase;
use Livewire\Component;

/**
 * Raw-material purchases, newest first, each identified by the date and time it was entered
 * (8 Oct change list, section 13). Rule 1: a purchases row is never edited after it is written.
 */
class PurchaseList extends Component
{
    use WithDataTable;

    protected function sortableColumns(): array
    {
        return ['created' => 'created_at', 'weight' => 'total_weight'];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    public function render()
    {
        $query = Purchase::with(['lines', 'order.customer:id,name', 'creator:id,name'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('invoice_number', 'like', "%{$this->search}%")->orWhere('notes', 'like', "%{$this->search}%")));

        return view('livewire.purchase.purchase-list', [
            'purchases' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => ['total' => Purchase::count(), 'weight' => Purchase::sum('total_weight')],
        ])->layout('components.layouts.app', ['title' => 'Purchases — Radharani Jewellery']);
    }
}
