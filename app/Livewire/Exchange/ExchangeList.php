<?php
namespace App\Livewire\Exchange;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Exchange\ExchangeTransaction;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every exchange (8 Oct change list, 9.1). Opening one continues it at the step it had reached.
 * Settled exchanges open read-only.
 */
class ExchangeList extends Component
{
    use WithDataTable;

    #[Url(except: 'open')]
    public string $show = 'open'; // open | settled | all

    protected function sortableColumns(): array
    {
        return ['id' => 'exchange_transactions.id', 'updated' => 'exchange_transactions.updated_at', 'weight' => 'exchange_transactions.gross_weight'];
    }

    protected function defaultSort(): array
    {
        return ['updated', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['show'];
    }

    public function hasNonDefaultFilters(): bool
    {
        return $this->search !== '' || $this->show !== 'open';
    }

    public function render()
    {
        $query = ExchangeTransaction::with('customer:id,name,phone')
            ->when($this->show === 'open', fn ($q) => $q->where('stage', '!=', 'settled'))
            ->when($this->show === 'settled', fn ($q) => $q->where('stage', 'settled'))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('exchange_transactions.id', ltrim($this->search, '#'))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")->orWhere('phone', 'like', "%{$this->search}%"))));

        return view('livewire.exchange.exchange-list', [
            'exchanges' => $this->applySorting($query)->paginate($this->perPageValue()),
            'open' => ExchangeTransaction::where('stage', '!=', 'settled')->count(),
        ])->layout('components.layouts.app', ['title' => 'Exchanges — Radharani Jewellery']);
    }
}
